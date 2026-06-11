<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_states', function (Blueprint $table) {
            $table->json('required_fields')->nullable()->after('requires_approval');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_states', function (Blueprint $table) {
            $table->dropColumn('required_fields');
        });
    }
};
