<?php

namespace Database\Seeders;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Sales\Models\DiningArea;
use App\Modules\Sales\Models\DiningTable;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;

class SalesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $tenant = Tenant::query()->where('slug', 'merkizi-demo')->firstOrFail();
        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $category = Category::query()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Ventas Demo'], ['status' => 'active']);
            $subcategory = Category::query()->firstOrCreate(['tenant_id' => $tenant->id, 'name' => 'Platos Demo'], ['parent_id' => $category->id, 'status' => 'active']);
            $product = Product::query()->firstOrCreate(['tenant_id' => $tenant->id, 'sku' => 'DEMO-PLATO'], ['category_id' => $subcategory->id, 'name' => 'Plato Demo', 'price' => '25.00', 'status' => 'active']);
            $extra = Product::query()->firstOrCreate(['tenant_id' => $tenant->id, 'sku' => 'DEMO-EXTRA'], ['category_id' => $subcategory->id, 'name' => 'Extra queso', 'price' => '3.00', 'status' => 'active']);
            $product->extras()->syncWithoutDetaching([$extra->id => ['price' => '3.00', 'active' => true]]);
            ProductVariant::query()->firstOrCreate(['tenant_id' => $tenant->id, 'product_id' => $product->id, 'name' => 'Grande'], ['price' => '35.00', 'active' => true]);
            foreach (Branch::query()->where('tenant_id', $tenant->id)->get() as $branch) {
                $area = DiningArea::query()->firstOrCreate(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Salón principal']);
                foreach (['1', '2', '3'] as $number) {
                    DiningTable::query()->firstOrCreate(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'area_id' => $area->id, 'number' => $number], ['capacity' => 4]);
                }
                CashRegister::query()->firstOrCreate(['tenant_id' => $tenant->id, 'branch_id' => $branch->id, 'name' => 'Caja Demo'], ['status' => 'active']);
            }
        });
    }
}
