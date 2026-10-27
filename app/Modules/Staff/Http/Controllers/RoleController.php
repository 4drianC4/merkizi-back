<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Staff\Http\Requests\SaveRoleRequest;
use App\Modules\Staff\Http\Resources\RoleResource;
use App\Shared\Identity\Models\Role;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    use ResolvesTenant;

    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('roles.ver'), 403);

        return RoleResource::collection(Role::query()->where('name', '!=', Role::SuperAdmin)->with('permissions')->orderBy('name')->get());
    }

    public function store(SaveRoleRequest $request): JsonResponse
    {
        $role = DB::transaction(function () use ($request): Role {
            $role = Role::query()->create([...$request->safe()->only(['name', 'description']), 'tenant_id' => $this->tenant()->id]);
            $role->permissions()->sync($request->validated('permission_ids'));

            return $role;
        });

        return (new RoleResource($role->load('permissions')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Role $role): RoleResource
    {
        abort_if($role->isSuperAdmin(), 404);
        abort_unless($request->user()->hasPermission('roles.ver'), 403);

        return new RoleResource($role->load('permissions'));
    }

    public function update(SaveRoleRequest $request, Role $role): RoleResource
    {
        DB::transaction(function () use ($request, $role): void {
            $role->update($request->safe()->only(['name', 'description']));
            $role->permissions()->sync($request->validated('permission_ids'));
        });

        return new RoleResource($role->load('permissions'));
    }

    public function destroy(Request $request, Role $role): Response
    {
        abort_unless($role->tenant_id === $this->tenant()->id && $request->user()->hasPermission('roles.eliminar'), 403);
        abort_if(User::withTrashed()->where('role_id', $role->id)->exists(), 422, 'This role is assigned to users.');
        $role->delete();

        return response()->noContent();
    }
}
