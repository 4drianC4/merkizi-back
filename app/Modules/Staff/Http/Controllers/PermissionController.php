<?php

namespace App\Modules\Staff\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Staff\Http\Resources\PermissionResource;
use App\Shared\Identity\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->hasPermission('roles.ver') || $request->user()->hasPermission('usuarios.permisos'), 403);

        return PermissionResource::collection(Permission::query()->orderBy('code')->get());
    }
}
