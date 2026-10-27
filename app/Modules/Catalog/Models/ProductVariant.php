<?php

namespace App\Modules\Catalog\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use Database\Factories\Modules\Catalog\Models\ProductVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = ['tenant_id', 'product_id', 'name', 'price', 'active'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['price' => 'decimal:2', 'active' => 'boolean'];
    }
}
