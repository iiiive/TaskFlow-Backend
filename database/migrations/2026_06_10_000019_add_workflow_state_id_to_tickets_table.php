<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('workflow_state_id')
                ->nullable()
                ->after('kanban_column_id')
                ->constrained('workflow_states')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['workflow_state_id']);
            $table->dropColumn('workflow_state_id');
        });
    }
};
