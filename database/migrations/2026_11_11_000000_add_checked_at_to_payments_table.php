<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->timestamp('checked_at')->nullable()->after('verified_at');
        });

        DB::table('payments')->whereNotNull('verified_at')->update(['checked_at' => DB::raw('verified_at')]);
        DB::table('payments')->where('status', 'rejected')->whereNull('checked_at')->update(['checked_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('checked_at');
        });
    }
};
