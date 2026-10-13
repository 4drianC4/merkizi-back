<?php

namespace App\Shared\Tenancy\Models;

use Database\Factories\Shared\Tenancy\Models\PlanLimitFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanLimit extends Model
{
    /** @use HasFactory<PlanLimitFactory> */
    use HasFactory;

    protected $fillable = [
        'plan_id',
        'key',
        'value',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
