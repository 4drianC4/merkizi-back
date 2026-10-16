<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cash_register_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->decimal('amount', 14, 2);
            $table->text('reason');
            $table->string('reference', 50)->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('cash_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
