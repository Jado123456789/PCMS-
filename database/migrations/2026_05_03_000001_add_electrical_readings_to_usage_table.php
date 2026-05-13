<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usage', function (Blueprint $table) {
            if (! Schema::hasColumn('usage', 'current')) {
                $table->decimal('current', 8, 2)->nullable()->after('kwh');
            }

            if (! Schema::hasColumn('usage', 'voltage')) {
                $table->decimal('voltage', 8, 2)->nullable()->after('current');
            }

            if (! Schema::hasColumn('usage', 'power')) {
                $table->decimal('power', 10, 2)->nullable()->after('voltage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('usage', function (Blueprint $table) {
            if (Schema::hasColumn('usage', 'power')) {
                $table->dropColumn('power');
            }

            if (Schema::hasColumn('usage', 'voltage')) {
                $table->dropColumn('voltage');
            }

            if (Schema::hasColumn('usage', 'current')) {
                $table->dropColumn('current');
            }
        });
    }
};
