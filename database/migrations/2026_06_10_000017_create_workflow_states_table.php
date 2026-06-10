<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained('workflow_templates')->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('#547A95');
            $table->unsignedSmallInteger('position')->default(1);
            $table->boolean('is_initial')->default(false);
            $table->boolean('is_final')->default(false);
            $table->boolean('requires_approval')->default(false);
            $table->timestamps();

            $table->index(['workflow_template_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_states');
    }
};
