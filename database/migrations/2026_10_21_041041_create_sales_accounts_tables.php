<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("CREATE UNIQUE INDEX cash_sessions_one_open_unique ON cash_sessions (cash_register_id) WHERE status = 'open'");
        Schema::create('dining_areas', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->string('name', 80);
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['branch_id', 'name']);
        });
        Schema::create('dining_tables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->uuid('area_id');
            $table->string('number', 20);
            $table->unsignedInteger('capacity')->default(4);
            $table->boolean('active')->default(true);
            $table->boolean('blocked')->default(false);
            $table->timestampsTz();
            $table->foreign(['tenant_id', 'branch_id', 'area_id'])->references(['tenant_id', 'branch_id', 'id'])->on('dining_areas')->restrictOnDelete();
            $table->unique(['area_id', 'number']);
        });
        Schema::create('table_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->foreignUuid('cash_session_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('opened_by')->constrained('users')->restrictOnDelete();
            $table->string('service_type')->default('table');
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_tax_id', 30)->nullable();
            $table->unsignedInteger('guests')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->decimal('tip', 14, 2)->default(0);
            $table->unsignedInteger('version')->default(1);
            // Crear la columna aquí; agregar su clave foránea después de crear la tabla.
            $table->uuid('destination_account_id')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();
            $table->index(['tenant_id', 'branch_id', 'status']);
        });
        // PostgreSQL necesita que la clave primaria de table_accounts ya exista.
        Schema::table('table_accounts', function (Blueprint $table): void {
            $table->foreign('destination_account_id')
                ->references('id')->on('table_accounts')->restrictOnDelete();
        });
        Schema::create('account_tables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->uuid('account_id');
            $table->uuid('table_id');
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampTz('separated_at')->nullable();
            $table->foreign(['tenant_id', 'branch_id', 'account_id'])->references(['tenant_id', 'branch_id', 'id'])->on('table_accounts')->restrictOnDelete();
            $table->foreign(['tenant_id', 'branch_id', 'table_id'])->references(['tenant_id', 'branch_id', 'id'])->on('dining_tables')->restrictOnDelete();
        });
        DB::statement('CREATE UNIQUE INDEX account_tables_active_unique ON account_tables (table_id) WHERE separated_at IS NULL');
        Schema::table('orders', function (Blueprint $table): void {
            $table->uuid('account_id')->nullable();
            $table->foreignUuid('origin_table_id')->nullable()->constrained('dining_tables')->restrictOnDelete();
            $table->foreignUuid('responsible_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('number')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('service_type')->default('takeaway');
            $table->text('notes')->nullable();
            $table->foreign(['tenant_id', 'branch_id', 'account_id'])->references(['tenant_id', 'branch_id', 'id'])->on('table_accounts')->restrictOnDelete();
            $table->unique(['branch_id', 'number']);
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->text('notes')->nullable();
            $table->uuid('variant_id')->nullable();
        });
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('price', 14, 2);
            $table->boolean('active')->default(true);
            $table->timestampsTz();
            $table->unique(['product_id', 'name']);
        });
        Schema::create('product_extras', function (Blueprint $table): void {
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('extra_product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('price', 14, 2)->nullable();
            $table->boolean('active')->default(true);
            $table->primary(['product_id', 'extra_product_id']);
        });
        Schema::create('sales', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->foreignUuid('account_id')->unique()->constrained('table_accounts')->restrictOnDelete();
            $table->foreignUuid('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('cashier_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->string('service_type');
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_tax_id', 30)->nullable();
            $table->string('status')->default('completed');
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount', 14, 2);
            $table->decimal('tax', 14, 2);
            $table->decimal('tip', 14, 2);
            $table->decimal('total', 14, 2);
            $table->uuid('close_key');
            $table->timestampsTz();
            $table->unique(['branch_id', 'number']);
            $table->index(['tenant_id', 'branch_id', 'created_at']);
        });
        Schema::create('account_payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->foreignUuid('account_id')->constrained('table_accounts')->restrictOnDelete();
            $table->foreignUuid('origin_account_id')->constrained('table_accounts')->restrictOnDelete();
            $table->foreignUuid('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('method');
            $table->decimal('amount', 14, 2);
            $table->decimal('received_amount', 14, 2)->nullable();
            $table->decimal('change', 14, 2)->default(0);
            $table->string('status');
            $table->string('reference', 100)->nullable();
            $table->uuid('idempotency_key');
            $table->unsignedInteger('batch_index');
            $table->string('request_hash', 64);
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['origin_account_id', 'idempotency_key', 'batch_index'], 'account_payments_idempotency_unique');
            $table->index(['account_id', 'status']);
        });
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('order_item_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->decimal('quantity', 14, 3);
            $table->decimal('price', 14, 2);
            $table->decimal('subtotal', 14, 2);
            $table->json('extras')->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
        Schema::create('sale_refunds', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->foreignUuid('sale_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('cash_session_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->decimal('amount', 14, 2);
            $table->string('method')->default('cash');
            $table->uuid('idempotency_key');
            $table->string('request_hash', 64);
            $table->timestampsTz();
            $table->unique(['sale_id', 'idempotency_key']);
        });
        Schema::create('sales_audits', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('branch_id');
            $table->foreign(['tenant_id', 'branch_id'])->references(['tenant_id', 'id'])->on('branches')->restrictOnDelete();
            $table->unique(['tenant_id', 'branch_id', 'id']);
            $table->foreignUuid('account_id')->constrained('table_accounts')->restrictOnDelete();
            $table->foreignUuid('user_id')->constrained()->restrictOnDelete();
            $table->string('action', 60);
            $table->json('data');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS cash_sessions_one_open_unique');
        foreach (['sales_audits', 'sale_refunds', 'sale_items', 'account_payments', 'sales', 'product_extras', 'product_variants'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('order_items', fn (Blueprint $table) => $table->dropColumn(['notes', 'variant_id']));
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropForeign(['tenant_id', 'branch_id', 'account_id']);
            $table->dropForeign(['origin_table_id']);
            $table->dropForeign(['responsible_user_id']);
            $table->dropUnique(['branch_id', 'number']);
            $table->dropColumn(['account_id', 'origin_table_id', 'responsible_user_id', 'number', 'version', 'service_type', 'notes']);
        });
        Schema::dropIfExists('account_tables');
        Schema::dropIfExists('table_accounts');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('dining_areas');
    }
};
