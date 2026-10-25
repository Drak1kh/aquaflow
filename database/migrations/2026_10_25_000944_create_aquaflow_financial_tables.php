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
        Schema::create('water_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->date('effective_from');
            $table->decimal('base_charge', 12, 2);
            $table->decimal('unit_rate', 12, 4);
            $table->timestamps();
            $table->unique(['water_provider_id', 'effective_from']);
        });

        Schema::create('billing_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('due_on');
            $table->timestamps();
            $table->unique(['water_provider_id', 'starts_on', 'ends_on']);
        });

        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained();
            $table->foreignId('water_connection_id')->constrained();
            $table->foreignId('billing_period_id')->constrained();
            $table->foreignId('meter_reading_id')->constrained();
            $table->foreignId('generated_by')->constrained('users');
            $table->string('bill_number');
            $table->decimal('previous_reading', 14, 3);
            $table->decimal('current_reading', 14, 3);
            $table->decimal('consumption', 14, 3);
            $table->decimal('base_charge', 12, 2);
            $table->decimal('unit_rate', 12, 4);
            $table->decimal('consumption_charge', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->date('due_on');
            $table->timestamp('late_fee_applied_at')->nullable();
            $table->string('status')->default('unpaid');
            $table->timestamps();
            $table->unique(['water_provider_id', 'bill_number']);
            $table->unique(['water_connection_id', 'billing_period_id']);
            $table->index(['water_provider_id', 'status', 'due_on']);
        });

        Schema::create('bill_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_id')->constrained();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained();
            $table->foreignId('recorded_by')->constrained('users');
            $table->foreignId('intended_bill_id')->constrained('bills');
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->string('reference_number');
            $table->decimal('amount', 12, 2);
            $table->string('method');
            $table->string('status')->default('pending');
            $table->string('proof_path')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
            $table->unique(['water_provider_id', 'reference_number']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained();
            $table->foreignId('bill_id')->constrained();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['payment_id', 'bill_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('bill_adjustments');
        Schema::dropIfExists('bills');
        Schema::dropIfExists('billing_periods');
        Schema::dropIfExists('water_rates');
    }
};
