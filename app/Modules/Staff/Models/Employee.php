<?php

namespace App\Modules\Staff\Models;

use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\HasTenantScope;
use Database\Factories\Modules\Staff\Models\EmployeeFactory;

/** Employees are company users, sharing their identity, role and branch assignments. */
class Employee extends User
{
    use HasTenantScope;

    protected $table = 'users';

    public function getMorphClass(): string
    {
        return User::class;
    }

    protected static function newFactory(): EmployeeFactory
    {
        return EmployeeFactory::new();
    }
}
