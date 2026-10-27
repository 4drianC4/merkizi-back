<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Models\SalesAudit;
use App\Modules\Sales\Models\TableAccount;
use App\Shared\Exceptions\DomainException;
use App\Shared\Identity\Models\User;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Support\Facades\DB;

class ManageAccountOrders
{
    public function __construct(private readonly BuildOrderItems $builder) {}

    /** @param array<string, mixed> $data */
    public function create(TableAccount $account, User $user, array $data): Order
    {
        return DB::transaction(function () use ($account, $user, $data): Order {
            $account = TableAccount::query()->lockForUpdate()->findOrFail($account->id);
            $account->assertOpen();
            $branch = Branch::query()->lockForUpdate()->findOrFail($account->branch_id);
            $items = $this->builder->handle($branch, $data['items']);
            $order = Order::query()->create([
                'tenant_id' => $account->tenant_id, 'branch_id' => $account->branch_id, 'account_id' => $account->id,
                'cash_session_id' => $account->cash_session_id, 'responsible_user_id' => $user->id,
                'origin_table_id' => $account->tables()->first()?->id, 'number' => ((int) Order::query()->where('branch_id', $branch->id)->max('number')) + 1,
                'service_type' => $account->service_type, 'customer_name' => $account->customer_name, 'status' => 'draft',
                'notes' => $data['notes'] ?? null, ...$this->amounts($items, $data),
            ]);
            $order->items()->createMany($items);
            $account->increment('version');
            SalesAudit::record($account, $user, 'order.created', ['order_id' => $order->id, 'items' => $items, 'total' => $order->total]);

            return $order->load('items');
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function update(Order $order, User $user, array $data): Order
    {
        return DB::transaction(function () use ($order, $user, $data): Order {
            $account = TableAccount::query()->lockForUpdate()->findOrFail($order->account_id);
            $account->assertOpen();
            $order = Order::query()->with('items')->lockForUpdate()->findOrFail($order->id);
            if ((int) $order->version !== (int) $data['version']) {
                throw new DomainException('Reload the order before editing it.', 'version_conflict', 409);
            }
            $before = $order->items->toArray();
            $items = [];
            $seen = [];
            foreach ($data['items'] as $line) {
                if (! empty($line['id'])) {
                    $item = $order->items->firstWhere('id', $line['id']);
                    if ($item === null || isset($seen[$line['id']]) || isset($line['product_id']) || isset($line['variant_id']) || isset($line['extras'])) {
                        throw new DomainException('Existing items accept only id, quantity and notes.', 'invalid_order_item');
                    }
                    $seen[$line['id']] = true;
                    $itemData = $item->only(['product_id', 'variant_id', 'name', 'price', 'quantity', 'extras', 'notes', 'subtotal']);
                    $itemData['quantity'] = (string) $line['quantity'];
                    $itemData['notes'] = $line['notes'] ?? $item->notes;
                    $itemData['subtotal'] = bcadd(bcmul((string) $item->price, (string) $line['quantity'], 4), '0.005', 2);
                    $items[] = $itemData;
                } else {
                    $items = [...$items, ...$this->builder->handle($account->branch, [$line])];
                }
            }
            $amounts = $this->amounts($items, ['discount' => $data['discount'] ?? $order->discount, 'tax' => $data['tax'] ?? $order->tax]);
            $totals = $account->totals();
            $newTotal = Money::add(Money::sub($totals['total'], (string) $order->total), $amounts['total']);
            if (Money::cmp($newTotal, Money::add($totals['paid'], $totals['pending'])) < 0) {
                throw new DomainException('The new total cannot be less than confirmed and pending payments.', 'payments_exceed_total', 409);
            }
            $order->items()->delete();
            $order->items()->createMany($items);
            $order->update([...$amounts, 'version' => $order->version + 1, 'notes' => $data['notes'] ?? $order->notes]);
            $account->increment('version');
            SalesAudit::record($account, $user, 'order.items_updated', ['order_id' => $order->id, 'before' => $before, 'after' => $items]);

            return $order->load('items');
        }, 3);
    }

    /** @param list<array<string, mixed>> $items
     * @param  array<string, mixed>  $data
     * @return array{subtotal: string, discount: string, tax: string, total: string}
     */
    public function amounts(array $items, array $data): array
    {
        $subtotal = '0.00';
        foreach ($items as $item) {
            $subtotal = Money::add($subtotal, (string) $item['subtotal']);
        }
        $discount = (string) ($data['discount'] ?? '0.00');
        $tax = (string) ($data['tax'] ?? '0.00');
        if (Money::cmp($discount, '0') < 0 || Money::cmp($tax, '0') < 0 || Money::cmp($discount, $subtotal) > 0) {
            throw new DomainException('Discount must be between zero and subtotal; tax cannot be negative.', 'invalid_totals');
        }
        $total = Money::sub(Money::add($subtotal, $tax), $discount);

        return compact('subtotal', 'discount', 'tax', 'total');
    }
}
