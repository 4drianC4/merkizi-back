<?php

namespace Database\Factories\Modules\Staff\Models;

use App\Modules\Staff\Models\Employee;
use Database\Factories\Shared\Identity\Models\UserFactory;

/** @extends UserFactory */
class EmployeeFactory extends UserFactory
{
    protected $model = Employee::class;
}
