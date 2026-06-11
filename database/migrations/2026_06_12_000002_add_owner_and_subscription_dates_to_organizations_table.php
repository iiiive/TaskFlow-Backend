<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (!Schema::hasColumn('organizations', 'owner_id')) {
                $table->foreignId('owner_id')
                    ->nullable()
                    ->after('owner_email')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('organizations', 'subscription_starts_at')) {
                $table->timestamp('subscription_starts_at')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('organizations', 'subscription_ends_at')) {
                $table->timestamp('subscription_ends_at')->nullable()->after('subscription_starts_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            if (Schema::hasColumn('organizations', 'owner_id')) {
                $table->dropConstrainedForeignId('owner_id');
            }

            foreach (['subscription_starts_at', 'subscription_ends_at'] as $column) {
                if (Schema::hasColumn('organizations', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
