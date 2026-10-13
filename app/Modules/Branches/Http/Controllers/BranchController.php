<?php

namespace App\Modules\Branches\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Branches\Actions\CreateBranch;
use App\Modules\Branches\Actions\UpdateBranch;
use App\Modules\Branches\Http\Requests\StoreBranchRequest;
use App\Modules\Branches\Http\Requests\UpdateBranchRequest;
use App\Modules\Branches\Http\Resources\BranchResource;
use App\Shared\Tenancy\Concerns\ResolvesTenant;
use App\Shared\Tenancy\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BranchController extends Controller
{
    use ResolvesTenant;

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        return BranchResource::collection($this->tenant()->branches()->latest()->get());
    }

    public function store(StoreBranchRequest $request, CreateBranch $createBranch): JsonResponse
    {
        $branch = $createBranch->handle($this->tenant(), $request->validated('name'));

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch);
    }

    public function update(UpdateBranchRequest $request, Branch $branch, UpdateBranch $updateBranch): BranchResource
    {
        $branch = $updateBranch->handle($branch, $request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): Response
    {
        $this->authorize('delete', $branch);

        $branch->update(['status' => 'inactive']);

        return response()->noContent();
    }
}
