<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('water_providers', function (Blueprint $table) {
            $table->unsignedBigInteger('account_sequence')->nullable();
            $table->unsignedBigInteger('connection_sequence')->nullable();
            $table->unsignedBigInteger('meter_sequence')->nullable();
        });

        DB::table('water_providers')->orderBy('id')->chunkById(100, function ($providers): void {
            foreach ($providers as $provider) {
                $sequences = [];
                foreach ([['consumers', 'account_number', 'ACC', 'account_sequence'], ['connections', 'number', 'CN', 'connection_sequence'], ['connections', 'meter_number', 'MTR', 'meter_sequence']] as [$table, $column, $prefix, $counter]) {
                    $sequences[$counter] = 0;
                    foreach (DB::table($table)->where('water_provider_id', $provider->id)->where($column, 'like', $prefix.'-%')->pluck($column) as $identifier) {
                        if (preg_match('/^'.$prefix.'-(\d+)$/D', $identifier, $matches)) {
                            $sequences[$counter] = max($sequences[$counter], (int) $matches[1]);
                        }
                    }
                }
                DB::table('water_providers')->where('id', $provider->id)->update($sequences);
            }
        });
    }

    public function down(): void
    {
        Schema::table('water_providers', function (Blueprint $table) {
            $table->dropColumn(['account_sequence', 'connection_sequence', 'meter_sequence']);
        });
    }
};
