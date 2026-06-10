<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')
                ->constrained('tickets')
                ->cascadeOnDelete();
            $table->foreignId('label_id')
                ->constrained('labels')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'label_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_labels');
    }
};
