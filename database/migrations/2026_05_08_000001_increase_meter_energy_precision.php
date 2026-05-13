<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE `usage` MODIFY `kwh` DECIMAL(12,6) NOT NULL');
        DB::statement('ALTER TABLE `meter_status` MODIFY `unit` DECIMAL(12,6) NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `usage` MODIFY `kwh` DECIMAL(8,2) NOT NULL');
        DB::statement('ALTER TABLE `meter_status` MODIFY `unit` DECIMAL(8,2) NOT NULL');
    }
};
