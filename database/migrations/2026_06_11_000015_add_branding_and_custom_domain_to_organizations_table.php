<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('owner_email');
            $table->string('primary_color', 20)->nullable()->after('logo_path');
            $table->string('custom_domain')->nullable()->unique()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropUnique(['custom_domain']);
            $table->dropColumn(['logo_path', 'primary_color', 'custom_domain']);
        });
    }
};
