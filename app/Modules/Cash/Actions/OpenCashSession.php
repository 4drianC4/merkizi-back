<?php

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Identity\Models\User;
use Illuminate\Support\Facades\DB;

class OpenCashSession
{
    public function handle(CashRegister $register, User $user, string $openingBalance): CashSession
    {
        return DB::transaction(function () use ($register, $user, $openingBalance) {
            $register = CashRegister::query()->lockForUpdate()->findOrFail($register->id);
            if ($register->status !== 'active') {
                throw new InvalidStateTransitionException('The cash register is inactive.');
            }
            $existing = $register->sessions()
                ->where('status', 'open')
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw new InvalidStateTransitionException('The cash register already has an open session.');
            }

            $session = CashSession::query()->create([
                'tenant_id' => $register->tenant_id,
                'branch_id' => $register->branch_id,
                'cash_register_id' => $register->id,
                'opened_by' => $user->id,
                'opened_at' => now(),
                'opening_balance' => $openingBalance,
                'status' => 'open',
            ]);

            return $session;
        });
    }
}
