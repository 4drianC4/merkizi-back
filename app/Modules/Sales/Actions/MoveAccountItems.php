<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\OrderItem;
use App\Modules\Sales\Models\SalesAudit;
use App\Modules\Sales\Models\TableAccount;
use App\Shared\Exceptions\DomainException;
use App\Shared\Identity\Models\User;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MoveAccountItems
{
    /** @param array<string, mixed> $data */
    public function handle(User $user, array $data, bool $merge): TableAccount
    {
        return DB::transaction(function () use ($user, $data, $merge): TableAccount {
            $accounts = TableAccount::query()->whereIn('id', [$data['source_account_id'], $data['destination_account_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $accounts->get($data['source_account_id']);
            $target = $accounts->get($data['destination_account_id']);
            if ($source === null || $target === null) {
                abort(404);
            }
            if ($source->id === $target->id || $source->branch_id !== $target->branch_id || $source->service_type !== 'table' || $target->service_type !== 'table') {
                throw new DomainException('Choose two different table accounts in the same branch.', 'invalid_destination');
            }
            abort_unless($user->hasPermission($merge ? 'salon.mesas.unir' : 'salon.mesas.transferir', $source->branch), 403);
            $source->assertOpen();
            $target->assertOpen();
            if ((int) $source->version !== (int) $data['source_version'] || (int) $target->version !== (int) $data['destination_version']) {
                throw new DomainException('Reload both accounts before moving items.', 'version_conflict', 409);
            }
            $before = $source->totals();
            if ($merge) {
                $orderIds = $source->orders()->pluck('id')->all();
                $paymentIds = $source->payments()->pluck('id')->all();
                $source->orders()->update(['account_id' => $target->id]);
                $source->payments()->update(['account_id' => $target->id]);
                $tableLinks = DB::table('account_tables')->where('account_id', $source->id)->whereNull('separated_at')->get();
                DB::table('account_tables')->where('account_id', $source->id)->whereNull('separated_at')->update(['separated_at' => now()]);
                foreach ($tableLinks as $link) {
                    DB::table('account_tables')->insert(['id' => (string) Str::uuid(), 'tenant_id' => $target->tenant_id,
                        'branch_id' => $target->branch_id, 'account_id' => $target->id, 'table_id' => $link->table_id, 'joined_at' => now()]);
                }
                $target->tip = Money::add((string) $target->tip, (string) $source->tip);
                if ($target->guests !== null || $source->guests !== null) {
                    $target->guests = ($target->guests ?? 0) + ($source->guests ?? 0);
                }
                $source->update(['status' => 'merged', 'destination_account_id' => $target->id, 'closed_at' => now(), 'tip' => '0.00', 'version' => $source->version + 1]);
                $audit = ['source_account_id' => $source->id, 'destination_account_id' => $target->id, 'order_ids' => $orderIds, 'payment_ids' => $paymentIds, 'totals' => $before, 'source_table_ids' => $tableLinks->pluck('table_id')->all()];
            } else {
                $audit = $this->transfer($source, $target, $user, $data);
                $source->increment('version');
            }
            $target->version++;
            $target->save();
            $action = $merge ? 'tables.merged' : 'items.transferred';
            SalesAudit::record($source, $user, $action, $audit);
            SalesAudit::record($target, $user, $action, $audit);

            return $target->fresh();
        }, 3);
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function transfer(TableAccount $source, TableAccount $target, User $user, array $data): array
    {
        $orders = $source->orders()->with('items')->get()->keyBy('id');
        $items = $orders->flatMap(fn (Order $order) => $order->items)->keyBy('id');
        $lines = ! empty($data['transfer_all']) ? $items->map(fn (OrderItem $item): array => ['id' => $item->id, 'quantity' => $item->quantity])->values()->all() : $data['items'];
        if ($lines === []) {
            throw new DomainException('Select at least one item.', 'items_required');
        }
        Branch::query()->lockForUpdate()->findOrFail($source->branch_id);
        $newOrder = Order::query()->create(['tenant_id' => $target->tenant_id, 'branch_id' => $target->branch_id,
            'account_id' => $target->id, 'cash_session_id' => $target->cash_session_id, 'responsible_user_id' => $user->id,
            'origin_table_id' => $source->tables()->first()?->id, 'service_type' => 'table', 'status' => 'draft',
            'number' => ((int) Order::query()->where('branch_id', $target->branch_id)->max('number')) + 1]);
        $moved = [];
        $subtotals = [];
        foreach ($lines as $line) {
            $item = $items->get($line['id']);
            $quantity = (string) $line['quantity'];
            if ($item === null || bccomp($quantity, (string) $item->quantity, 3) > 0 || bccomp($quantity, '0', 3) <= 0) {
                throw new DomainException('The selected quantity is not available in the source account.', 'invalid_transfer_item');
            }
            $orderId = $item->order_id;
            $subtotal = bccomp($quantity, (string) $item->quantity, 3) === 0 ? (string) $item->subtotal : bcadd(bcmul((string) $item->price, $quantity, 4), '0.005', 2);
            if (Money::cmp($subtotal, (string) $item->subtotal) > 0) {
                $subtotal = (string) $item->subtotal;
            }
            $subtotals[$orderId] = Money::add($subtotals[$orderId] ?? '0.00', $subtotal);
            if (bccomp($quantity, (string) $item->quantity, 3) === 0) {
                $item->update(['order_id' => $newOrder->id]);
                $destinationId = $item->id;
            } else {
                $destination = $newOrder->items()->create([...$item->only(['product_id', 'variant_id', 'name', 'price', 'extras', 'notes']), 'quantity' => $quantity, 'subtotal' => $subtotal]);
                $destinationId = $destination->id;
                $item->update(['quantity' => bcsub((string) $item->quantity, $quantity, 3), 'subtotal' => Money::sub((string) $item->subtotal, $subtotal)]);
            }
            $moved[] = ['source_item_id' => $item->id, 'destination_item_id' => $destinationId, 'quantity' => $quantity, 'subtotal' => $subtotal];
        }
        $discount = $tax = $subtotal = '0.00';
        foreach ($subtotals as $orderId => $movedSubtotal) {
            $order = $orders[$orderId];
            $ratio = Money::cmp((string) $order->subtotal, '0') === 0 ? '0' : bcdiv($movedSubtotal, (string) $order->subtotal, 12);
            $movedDiscount = bcadd(bcmul((string) $order->discount, $ratio, 4), '0.005', 2);
            $movedTax = bcadd(bcmul((string) $order->tax, $ratio, 4), '0.005', 2);
            $subtotal = Money::add($subtotal, $movedSubtotal);
            $discount = Money::add($discount, $movedDiscount);
            $tax = Money::add($tax, $movedTax);
            $remainingSubtotal = Money::sub((string) $order->subtotal, $movedSubtotal);
            $remainingDiscount = Money::sub((string) $order->discount, $movedDiscount);
            $remainingTax = Money::sub((string) $order->tax, $movedTax);
            $order->update(['subtotal' => $remainingSubtotal, 'discount' => $remainingDiscount, 'tax' => $remainingTax,
                'total' => Money::sub(Money::add($remainingSubtotal, $remainingTax), $remainingDiscount), 'version' => $order->version + 1]);
        }
        $newOrder->update(['subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => Money::sub(Money::add($subtotal, $tax), $discount)]);
        $totals = $source->fresh()->totals();
        if (Money::cmp($totals['total'], Money::add($totals['paid'], $totals['pending'])) < 0) {
            throw new DomainException('Transferred items would leave existing payments without enough consumption.', 'payments_exceed_total', 409);
        }
        if (Money::cmp($totals['total'], '0') === 0 && $source->payments()->whereIn('status', ['confirmed', 'pending'])->doesntExist()) {
            $source->update(['status' => 'cancelled', 'closed_at' => now()]);
            DB::table('account_tables')->where('account_id', $source->id)->whereNull('separated_at')->update(['separated_at' => now()]);
        }

        return ['source_account_id' => $source->id, 'destination_account_id' => $target->id, 'destination_order_id' => $newOrder->id, 'items' => $moved,
            'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => $newOrder->total];
    }
}
