<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glint_aggregates', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10);
            $table->timestamp('period_at');
            $table->string('provider', 100);
            $table->string('model', 255);
            $table->string('user_id', 255)->nullable();
            $table->string('team_id', 255)->nullable();
            $table->unsignedInteger('total_requests')->default(0);
            $table->unsignedInteger('successful_requests')->default(0);
            $table->unsignedInteger('failed_requests')->default(0);
            $table->unsignedBigInteger('total_tokens')->default(0);
            $table->unsignedBigInteger('prompt_tokens')->default(0);
            $table->unsignedBigInteger('completion_tokens')->default(0);
            $table->decimal('total_cost_usd', 12, 6)->default(0);
            $table->unsignedInteger('avg_duration_ms')->nullable();
            $table->unsignedInteger('p95_duration_ms')->nullable();
            $table->unsignedInteger('p99_duration_ms')->nullable();
            $table->timestamps();

            $table->unique(['period', 'period_at', 'provider', 'model', 'user_id', 'team_id'], 'glint_agg_unique');
            $table->index('period_at');
            $table->index('period');
            $table->index('provider');
            $table->index('model');
            $table->index(['period', 'period_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glint_aggregates');
    }
};
