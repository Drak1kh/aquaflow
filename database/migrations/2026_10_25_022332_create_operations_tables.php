<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('water_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('due_days')->nullable();
            $table->unsignedSmallInteger('grace_days')->nullable();
            $table->decimal('late_fee', 14, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('consumers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->string('account_number');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['water_provider_id', 'account_number']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('water_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consumer_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->boolean('must_change_password')->default(false);
        });

        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->string('number');
            $table->string('meter_number');
            $table->string('status')->default('active');
            $table->date('installed_at')->nullable();
            $table->timestamps();
            $table->unique(['water_provider_id', 'number']);
            $table->unique(['water_provider_id', 'meter_number']);
        });

        Schema::create('water_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_charge', 14, 2);
            $table->decimal('unit_rate', 14, 2);
            $table->date('effective_from');
            $table->timestamps();
            $table->unique(['water_provider_id', 'effective_from']);
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->date('period_month');
            $table->decimal('previous_reading', 14, 3);
            $table->decimal('current_reading', 14, 3);
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['connection_id', 'period_month']);
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meter_reading_id')->constrained()->restrictOnDelete();
            $table->string('number');
            $table->date('period_month');
            $table->decimal('previous_reading', 14, 3);
            $table->decimal('current_reading', 14, 3);
            $table->decimal('consumption', 14, 3);
            $table->decimal('base_charge', 14, 2);
            $table->decimal('unit_rate', 14, 2);
            $table->decimal('consumption_charge', 14, 2);
            $table->decimal('late_fee', 14, 2)->default(0);
            $table->decimal('total', 14, 2);
            $table->date('due_at');
            $table->string('status')->default('unpaid');
            $table->timestamp('late_fee_applied_at')->nullable();
            $table->timestamps();
            $table->unique(['water_provider_id', 'number']);
            $table->unique(['connection_id', 'period_month']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->decimal('amount', 14, 2);
            $table->string('method');
            $table->string('status')->default('pending');
            $table->date('paid_at');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('remarks')->nullable();
            $table->string('proof_path')->nullable();
            $table->timestamps();
            $table->unique(['water_provider_id', 'reference']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'bill_id']);
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('subject');
            $table->text('description');
            $table->string('status')->default('open');
            $table->timestamp('escalated_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('internal')->default(false);
            $table->text('body');
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('role');
            $table->string('permission');
            $table->boolean('allowed')->default(false);
            $table->timestamps();
            $table->unique(['role', 'permission']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type');
            $table->string('subject_id');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('water_rates');
        Schema::dropIfExists('connections');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumer_id');
            $table->dropConstrainedForeignId('water_provider_id');
            $table->dropColumn(['is_active', 'must_change_password']);
        });
        Schema::dropIfExists('consumers');
        Schema::dropIfExists('water_providers');
    }
};
