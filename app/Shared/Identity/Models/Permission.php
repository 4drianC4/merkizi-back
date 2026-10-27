<?php

namespace App\Shared\Identity\Models;

use Database\Factories\Shared\Identity\Models\PermissionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['code', 'module', 'action', 'scope', 'description'];
}
