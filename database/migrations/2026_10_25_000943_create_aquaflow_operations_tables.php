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
        Schema::create('consumers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('account_number');
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('contact_number')->nullable();
            $table->text('service_address');
            $table->string('status')->default('active');
            $table->json('notification_preferences')->nullable();
            $table->timestamps();
            $table->unique(['water_provider_id', 'account_number']);
        });

        Schema::create('water_meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->string('meter_number');
            $table->string('type')->default('standard');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['water_provider_id', 'meter_number']);
        });

        Schema::create('water_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('consumer_id')->constrained();
            $table->foreignId('water_meter_id')->constrained();
            $table->string('connection_number');
            $table->text('service_address');
            $table->date('installed_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->unique(['water_provider_id', 'connection_number']);
            $table->unique('water_meter_id');
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('water_provider_id')->constrained()->cascadeOnDelete();
            $table->foreignId('water_connection_id')->constrained();
            $table->foreignId('water_meter_id')->constrained();
            $table->foreignId('entered_by')->constrained('users');
            $table->date('reading_date');
            $table->decimal('previous_reading', 14, 3);
            $table->decimal('current_reading', 14, 3);
            $table->timestamps();
            $table->unique(['water_connection_id', 'reading_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meter_readings');
        Schema::dropIfExists('water_connections');
        Schema::dropIfExists('water_meters');
        Schema::dropIfExists('consumers');
    }
};
