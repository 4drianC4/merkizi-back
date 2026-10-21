<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->exists()) {
            throw new RuntimeException('The identity schema requires an empty development database. Rebuild the test data with migrate:fresh --seed.');
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 50);
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['tenant_id', 'name']);
        });
        DB::statement('CREATE UNIQUE INDEX roles_system_name_unique ON roles (name) WHERE tenant_id IS NULL');

        Schema::create('permissions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code', 100)->unique();
            $table->string('module', 60);
            $table->string('action', 60);
            $table->enum('scope', ['SUCURSAL', 'EMPRESA'])->default('SUCURSAL');
            $table->text('description')->nullable();
            $table->timestampTz('created_at')->useCurrent();
        });
        Schema::create('permission_role', function (Blueprint $table): void {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignUuid('tenant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->string('phone', 20)->nullable();
            $table->text('photo_url')->nullable();
            $table->boolean('active')->default(true);
            $table->timestampTz('last_access_at')->nullable();
            $table->softDeletesTz();
            $table->unique(['tenant_id', 'id']);
        });
        Schema::table('branches', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'id']);
        });
        Schema::create('branch_user', function (Blueprint $table): void {
            $table->uuid('tenant_id');
            $table->uuid('user_id');
            $table->uuid('branch_id');
            $table->boolean('is_primary')->default(false);
            $table->primary(['user_id', 'branch_id']);
            $table->foreign(['tenant_id', 'user_id'])->references(['tenant_id', 'id'])->on('users')->cascadeOnDelete();
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->cascadeOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX branch_user_primary_unique ON branch_user (user_id) WHERE is_primary');

        Schema::create('permission_user', function (Blueprint $table): void {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->boolean('granted')->default(true);
            $table->foreignUuid('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['user_id', 'permission_id']);
        });

        Schema::drop('employees');
        Schema::drop('tenant_memberships');
    }

    public function down(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });
        Schema::create('employees', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('role')->default('employee');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::dropIfExists('permission_user');
        Schema::dropIfExists('branch_user');
        Schema::table('branches', fn (Blueprint $table) => $table->dropUnique(['tenant_id', 'id']));
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id']);
            $table->dropForeign(['role_id']);
            $table->dropUnique(['tenant_id', 'id']);
            $table->dropColumn(['tenant_id', 'role_id', 'phone', 'photo_url', 'active', 'last_access_at', 'deleted_at']);
        });
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
