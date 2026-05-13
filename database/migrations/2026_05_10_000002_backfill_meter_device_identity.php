<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meter_status') || ! Schema::hasColumn('meter_status', 'meter_number')) {
            return;
        }

        DB::table('meter_status')
            ->whereNull('meter_number')
            ->orderBy('user_id')
            ->get()
            ->each(function ($meter) {
                $updates = [
                    'meter_number' => str_pad((string) $meter->user_id, 16, '0', STR_PAD_LEFT),
                ];

                if (Schema::hasColumn('meter_status', 'device_name') && empty($meter->device_name)) {
                    $updates['device_name'] = 'Meter ' . $updates['meter_number'];
                }

                if (Schema::hasColumn('meter_status', 'device_status') && empty($meter->device_status)) {
                    $updates['device_status'] = ((int) ($meter->connected ?? 0) === 1) ? 'online' : 'offline';
                }

                DB::table('meter_status')
                    ->where('user_id', $meter->user_id)
                    ->update($updates);
            });
    }

    public function down(): void
    {
    }
};
