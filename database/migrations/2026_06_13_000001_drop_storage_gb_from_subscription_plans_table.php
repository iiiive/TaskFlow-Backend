<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('subscription_plans', 'storage_gb')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->dropColumn('storage_gb');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('subscription_plans', 'storage_gb')) {
            Schema::table('subscription_plans', function (Blueprint $table) {
                $table->integer('storage_gb')->nullable()->after('max_members');
            });
        }
    }
};
