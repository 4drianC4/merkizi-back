<?php

namespace App\Providers;

use App\Modules\Branches\Policies\BranchPolicy;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Cash\Policies\CashRegisterPolicy;
use App\Modules\Cash\Policies\CashSessionPolicy;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Policies\CategoryPolicy;
use App\Modules\Catalog\Policies\ProductPolicy;
use App\Modules\Catalog\Models\RawMaterialCategory;
use App\Modules\Catalog\Policies\RawMaterialCategoryPolicy;
use App\Modules\Sales\Models\Order;
use App\Modules\Sales\Policies\OrderPolicy;
use App\Modules\Staff\Models\Employee;
use App\Modules\Staff\Policies\EmployeePolicy;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(RawMaterialCategory::class, RawMaterialCategoryPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(CashRegister::class, CashRegisterPolicy::class);
        Gate::policy(CashSession::class, CashSessionPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
    }
}
