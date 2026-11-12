<?php

namespace App\Actions;

use App\Models\WaterProvider;
use Illuminate\Support\Facades\DB;

class ProviderIdentifiers
{
    /** Call within the transaction that saves the consumer and connection. */
    public function assign(int $providerId, array $data): array
    {
        $provider = WaterProvider::whereKey($providerId)->lockForUpdate()->firstOrFail();
        foreach (['account_number' => ['consumers', 'ACC', 'account_sequence'], 'number' => ['connections', 'CN', 'connection_sequence'], 'meter_number' => ['connections', 'MTR', 'meter_sequence']] as $field => [$table, $prefix, $counter]) {
            if (! array_key_exists($field, $data) || filled($data[$field])) {
                continue;
            }

            do {
                $provider->{$counter} = (int) $provider->{$counter} + 1;
                $identifier = $prefix.'-'.str_pad((string) $provider->{$counter}, 3, '0', STR_PAD_LEFT);
            } while (DB::table($table)->where('water_provider_id', $providerId)->where($field, $identifier)->exists());

            $data[$field] = $identifier;
        }
        $provider->save();

        return $data;
    }
}
