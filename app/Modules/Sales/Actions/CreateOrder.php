<?php

namespace App\Modules\Sales\Actions;

use App\Modules\Cash\Models\CashSession;
use App\Modules\Catalog\Models\Product;
use App\Modules\Sales\Models\Order;
use App\Shared\Exceptions\DomainException;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Support\Money;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Support\Facades\DB;

class CreateOrder
{
    /**
     * @param  array{items: array<int, array{product_id: int, quantity: string, extras?: array<string, mixed>|null}>, customer_name?: string|null, discount?: string|null, tax?: string|null}  $data
     */
    public function handle(Branch $branch, CashSession $session, array $data): Order
    {
        if ($session->status !== 'open') {
            throw new InvalidStateTransitionException('The cash session is not open.');
        }

        if ((int) $session->branch_id !== (int) $branch->id) {
            throw new DomainException('The cash session does not belong to the branch.', 'session_branch_mismatch');
        }

        return DB::transaction(function () use ($branch, $session, $data) {
            $productIds = array_map(
                fn (array $line): int => (int) $line['product_id'],
                $data['items']
            );

            $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

            $items = [];
            $subtotal = '0.00';

            foreach ($data['items'] as $line) {
                $product = $products->get((int) $line['product_id']);

                if ($product === null) {
                    throw new DomainException('Product not found.', 'product_not_found');
                }

                $price = $product->priceForBranch($branch);
                $quantity = (string) $line['quantity'];
                $lineSubtotal = Money::mul($price, $quantity);

                $items[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'price' => $price,
                    'quantity' => $quantity,
                    'extras' => $line['extras'] ?? null,
                    'subtotal' => $lineSubtotal,
                ];

                $subtotal = Money::add($subtotal, $lineSubtotal);
            }

            $discount = (string) ($data['discount'] ?? '0.00');
            $tax = (string) ($data['tax'] ?? '0.00');
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
