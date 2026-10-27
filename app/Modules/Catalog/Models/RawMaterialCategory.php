<?php

namespace App\Modules\Catalog\Models;

use App\Shared\Tenancy\Concerns\HasTenantScope;
use Database\Factories\Modules\Catalog\Models\RawMaterialCategoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RawMaterialCategory extends Model
{
    /** @use HasFactory<RawMaterialCategoryFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    protected $fillable = [
        'tenant_id',
        'parent_id',
        'name',
        'status',
    ];

    /**
     * @return BelongsTo<RawMaterialCategory, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(RawMaterialCategory::class, 'parent_id');
    }

    /**
     * @return HasMany<RawMaterialCategory, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(RawMaterialCategory::class, 'parent_id');
    }
}