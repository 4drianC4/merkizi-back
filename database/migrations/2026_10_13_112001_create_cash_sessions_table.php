<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opened_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('opened_at');
            $table->dateTime('closed_at')->nullable();
            $table->decimal('opening_balance', 14, 2);
            $table->decimal('expected_cash_balance', 14, 2)->nullable();
            $table->decimal('counted_cash_balance', 14, 2)->nullable();
            $table->decimal('cash_difference', 14, 2)->nullable();
            $table->text('difference_note')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('branch_id');
            $table->index('cash_register_id');
        });

        DB::statement("CREATE UNIQUE INDEX cash_sessions_register_active_unique ON cash_sessions (cash_register_id) WHERE status IN ('open', 'reconciliation_pending')");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
