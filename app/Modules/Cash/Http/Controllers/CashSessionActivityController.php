<?php

namespace App\Modules\Cash\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Cash\Actions\GetCashSessionActivity;
use App\Modules\Cash\Http\Resources\CashSessionActivityResource;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashSessionActivityController extends Controller
{
    public function index(
        CashSession $cashSession,
        GetCashSessionActivity $getCashSessionActivity,
    ): AnonymousResourceCollection
    {
        $this->authorize('view', $cashSession);

        return CashSessionActivityResource::collection(
            $getCashSessionActivity->handle($cashSession),
        );
    }
}
