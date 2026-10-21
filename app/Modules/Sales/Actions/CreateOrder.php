<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Sales\Models\Order;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Support\Facades\DB;

class CreateOrder
{
    public function __construct(private readonly BuildOrderItems $builder) {}

    /**
     * @param  array{items: array<int, array{product_id: string, quantity: string, variant_id?: string|null, extras?: list<array{product_id: string}>|null, notes?: string|null}>, customer_name?: string|null, discount?: string|null, tax?: string|null}  $data
     */
    public function handle(Branch $branch, CashSession $session, array $data): Order
    {
        if ($session->status !== 'open') {
            throw new InvalidStateTransitionException('The cash session is not open.');
        }

        if ($session->branch_id !== $branch->id) {
            throw new DomainException('The cash session does not belong to the branch.', 'session_branch_mismatch');
        }

        return DB::transaction(function () use ($branch, $session, $data) {
            $items = $this->builder->handle($branch, $data['items']);
            $subtotal = '0.00';
            foreach ($items as $item) {
                $subtotal = Money::add($subtotal, (string) $item['subtotal']);
            }

            $discount = (string) ($data['discount'] ?? '0.00');
            $tax = (string) ($data['tax'] ?? '0.00');
            if (Money::cmp($discount, $subtotal) > 0) {
                throw new DomainException('Discount cannot exceed subtotal.', 'invalid_totals');
            }
            $total = Money::sub(Money::add($subtotal, $tax), $discount);

            $order = Order::query()->create([
                'tenant_id' => $branch->tenant_id,
                'branch_id' => $branch->id,
                'cash_session_id' => $session->id,
                'customer_name' => $data['customer_name'] ?? null,
                'status' => 'draft',
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => $tax,
                'total' => $total,
            ]);

            $order->items()->createMany($items);

            return $order;
        });
    }
}
