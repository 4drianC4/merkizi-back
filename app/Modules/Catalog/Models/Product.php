<?php

namespace App\Modules\Catalog\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Catalog\Models\ProductFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasTenantScope;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'name',
        'sku',
        'price',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    /**
     * Resolve the effective price for a branch, falling back to the base price.
     */
    public function priceForBranch(?Branch $branch): string
    {
        if ($branch !== null) {
            $override = $this->prices()->where('branch_id', $branch->id)->first();

            if ($override !== null) {
                return (string) $override->price;
            }
        }

        return (string) $this->price;
    }
}
