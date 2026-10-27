<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Staff\Http\Requests\StoreEmployeeRequest;
use App\Modules\Staff\Http\Requests\UpdateEmployeePermissionsRequest;
use App\Modules\Staff\Http\Requests\UpdateEmployeeRequest;
use App\Modules\Staff\Http\Resources\EmployeeResource;
use App\Modules\Staff\Models\Employee;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    use ResolvesTenant;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Employee::class);

        return EmployeeResource::collection(Employee::query()->with(['role', 'branches', 'permissions'])->latest()->get());
    }

    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = DB::transaction(function () use ($request): Employee {
            $employee = Employee::query()->create([
                ...$request->safe()->except(['branch_ids', 'primary_branch_id']),
                'tenant_id' => $this->tenant()->id,
            ]);
            $this->syncBranches($employee, $request->validated('branch_ids'), $request->validated('primary_branch_id'));

            return $employee;
        });

        return (new EmployeeResource($employee->load(['role', 'branches', 'permissions'])))->response()->setStatusCode(201);
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);

        return new EmployeeResource($employee->load(['role', 'branches', 'permissions']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        DB::transaction(function () use ($request, $employee): void {
            $employee->update($request->safe()->except(['branch_ids', 'primary_branch_id']));
            if ($request->has('branch_ids')) {
                $this->syncBranches($employee, $request->validated('branch_ids'), $request->validated('primary_branch_id'));
            }
        });

        return new EmployeeResource($employee->load(['role', 'branches', 'permissions']));
    }

    public function destroy(Employee $employee): Response
    {
        $this->authorize('delete', $employee);
        abort_if($employee->id === auth()->id(), 403);
        DB::transaction(function () use ($employee): void {
            $employee->update(['active' => false]);
            $employee->tokens()->delete();
            $employee->delete();
        });

        return response()->noContent();
    }

    public function permissions(UpdateEmployeePermissionsRequest $request, Employee $employee): EmployeeResource
    {
        $overrides = [];
        foreach ($request->validated('permissions') as $permission) {
            $overrides[$permission['permission_id']] = ['granted' => $permission['granted'], 'granted_by_user_id' => $request->user()->id, 'created_at' => now()];
        }
        DB::transaction(fn () => $employee->permissions()->sync($overrides));

        return new EmployeeResource($employee->load(['role', 'branches', 'permissions']));
    }

    /** @param list<string> $branchIds */
    private function syncBranches(Employee $employee, array $branchIds, ?string $primaryBranchId): void
    {
        $employee->branches()->detach();
        $assignments = [];
        foreach ($branchIds as $branchId) {
            $assignments[$branchId] = ['tenant_id' => $employee->tenant_id, 'is_primary' => $branchId === ($primaryBranchId ?? $branchIds[0])];
        }
        $employee->branches()->attach($assignments);
    }
}
