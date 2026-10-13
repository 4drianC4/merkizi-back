<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Staff\Http\Requests\StoreEmployeeRequest;
use App\Modules\Staff\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Staff\Http\Resources\EmployeeResource;
use App\Modules\Staff\Models\Employee;
use App\Shared\Exceptions\DomainException;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class EmployeeController extends Controller
{
    use ResolvesTenant;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        return EmployeeResource::collection(Employee::query()->latest()->get());
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $branchId = $request->validated('branch_id');

        if ($branchId !== null && Branch::query()->whereKey($branchId)->doesntExist()) {
            throw new DomainException('Branch not found.', 'branch_not_found');
        }

        $employee = Employee::query()->create([
            'tenant_id' => $this->tenant()->id,
            'branch_id' => $branchId,
            'user_id' => $request->validated('user_id'),
            'name' => $request->validated('name'),
            'role' => $request->validated('role') ?? 'employee',
            'status' => 'active',
        ]);

        return (new EmployeeResource($employee))->response()->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        if ($request->has('branch_id')) {
            $branchId = $request->validated('branch_id');

            if ($branchId !== null && Branch::query()->whereKey($branchId)->doesntExist()) {
                throw new DomainException('Branch not found.', 'branch_not_found');
            }
        }

        $employee->update($request->validated());

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): Response
    {
        $this->authorize('delete', $employee);

        $employee->update(['status' => 'inactive']);

        return response()->noContent();
    }
}
