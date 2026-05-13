<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meter_status')) {
            Schema::table('meter_status', function (Blueprint $table) {
                if (! Schema::hasColumn('meter_status', 'meter_number')) {
                    $table->string('meter_number', 32)->nullable()->unique()->after('user_id');
                }

                if (! Schema::hasColumn('meter_status', 'device_name')) {
                    $table->string('device_name')->nullable()->after('meter_number');
                }

                if (! Schema::hasColumn('meter_status', 'device_status')) {
                    $table->string('device_status', 30)->default('offline')->after('connected');
                }

                if (! Schema::hasColumn('meter_status', 'location')) {
                    $table->string('location')->nullable()->after('device_status');
                }

                if (! Schema::hasColumn('meter_status', 'last_seen_at')) {
                    $table->timestamp('last_seen_at')->nullable()->after('location');
                }
            });
        }

        if (Schema::hasTable('usage')) {
            Schema::table('usage', function (Blueprint $table) {
                if (! Schema::hasColumn('usage', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->index()->after('id');
                }

                if (! Schema::hasColumn('usage', 'meter_number')) {
                    $table->string('meter_number', 32)->nullable()->index()->after('user_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('usage')) {
            Schema::table('usage', function (Blueprint $table) {
                if (Schema::hasColumn('usage', 'meter_number')) {
                    $table->dropIndex(['meter_number']);
                    $table->dropColumn('meter_number');
                }

                if (Schema::hasColumn('usage', 'user_id')) {
                    $table->dropIndex(['user_id']);
                    $table->dropColumn('user_id');
                }
            });
        }

        if (Schema::hasTable('meter_status')) {
            Schema::table('meter_status', function (Blueprint $table) {
                foreach (['last_seen_at', 'location', 'device_status', 'device_name'] as $column) {
                    if (Schema::hasColumn('meter_status', $column)) {
                        $table->dropColumn($column);
                    }
                }

                if (Schema::hasColumn('meter_status', 'meter_number')) {
                    $table->dropUnique(['meter_number']);
                    $table->dropColumn('meter_number');
                }
            });
        }
    }
};
