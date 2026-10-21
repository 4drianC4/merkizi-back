<?php

namespace App\Modules\Sales\Models;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\HasTenantScope;
use App\Shared\Tenancy\Models\Branch;
use Database\Factories\Modules\Sales\Models\SalesAuditFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesAudit extends Model
{
    /** @use HasFactory<SalesAuditFactory> */
    use HasFactory;

    use HasTenantScope;
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['tenant_id', 'branch_id', 'account_id', 'user_id', 'action', 'data'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @param array<string, mixed> $data */
    public static function record(TableAccount $account, User $user, string $action, array $data = []): self
    {
        return static::query()->create(['tenant_id' => $account->tenant_id, 'branch_id' => $account->branch_id,
            'account_id' => $account->id, 'user_id' => $user->id, 'action' => $action, 'data' => $data]);
    }
}
