<?php

namespace Database\Seeders;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashRegister;
use App\Modules\Cash\Models\CashSession;
use App\Shared\Identity\Models\User;
use App\Shared\Tenancy\Models\Branch;
use App\Shared\Tenancy\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CashDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'merkizi-demo')->firstOrFail();
        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        app(TenantContext::class)->run($tenant, function () use ($tenant, $user): void {
            DB::transaction(function () use ($tenant, $user): void {
                $branch = Branch::query()->firstOrCreate(
                    ['name' => 'Sucursal Demo'],
                    ['tenant_id' => $tenant->id, 'status' => 'active'],
                );

                $cashRegister = CashRegister::query()->firstOrCreate(
                    ['branch_id' => $branch->id, 'name' => 'Caja Principal'],
                    ['tenant_id' => $tenant->id, 'status' => 'active'],
                );

                CashRegister::query()->firstOrCreate(
                    ['branch_id' => $branch->id, 'name' => 'Caja Auxiliar'],
                    ['tenant_id' => $tenant->id, 'status' => 'active'],
                );

                $cashSession = CashSession::query()->firstOrCreate(
                    ['cash_register_id' => $cashRegister->id, 'status' => 'open'],
                    [
                        'tenant_id' => $tenant->id,
                        'branch_id' => $branch->id,
                        'opened_by' => $user->id,
                        'opened_at' => now()->subHour(),
                        'opening_balance' => '100.00',
                    ],
                );

                $movements = [
                    [
                        'type' => 'cash_in',
                        'amount' => '50.00',
                        'reason' => 'Reposición de cambio',
                        'reference' => 'SEED-CASH-IN-001',
                    ],
                    [
                        'type' => 'cash_out',
                        'amount' => '-20.00',
                        'reason' => 'Retiro para caja fuerte',
                        'reference' => 'SEED-CASH-OUT-001',
                    ],
                    [
                        'type' => 'expense',
                        'amount' => '-10.00',
                        'reason' => 'Compra de insumos urgentes',
                        'reference' => 'SEED-EXPENSE-001',
                    ],
                ];

                foreach ($movements as $movement) {
                    CashMovement::query()->firstOrCreate(
                        [
                            'cash_session_id' => $cashSession->id,
                            'reference' => $movement['reference'],
                        ],
                        [
                            'tenant_id' => $tenant->id,
                            'branch_id' => $branch->id,
                            'cash_register_id' => $cashRegister->id,
                            'recorded_by' => $user->id,
                            'type' => $movement['type'],
                            'amount' => $movement['amount'],
                            'reason' => $movement['reason'],
                        ],
                    );
                }
            });
        });
    }
}
