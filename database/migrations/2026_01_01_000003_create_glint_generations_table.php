<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glint_generations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('trace_id');
            $table->ulid('parent_span_id')->nullable();
            $table->string('name')->default('');
            $table->string('provider', 100);
            $table->string('model', 255);
            $table->longText('prompt')->nullable();
            $table->longText('completion')->nullable();
            $table->unsignedInteger('prompt_tokens')->nullable();
            $table->unsignedInteger('completion_tokens')->nullable();
            $table->unsignedInteger('total_tokens')->nullable();
            $table->decimal('cost_usd', 10, 8)->nullable();
            $table->decimal('temperature', 4, 2)->nullable();
            $table->unsignedInteger('max_tokens')->nullable();
            $table->decimal('top_p', 4, 2)->nullable();
            $table->string('finish_reason', 50)->nullable();
            $table->boolean('is_streaming')->default(false);
            $table->string('status', 10)->default('pending');
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('trace_id');
            $table->index(['provider', 'model']);
            $table->index('status');
            $table->index('started_at');
            $table->index(['started_at', 'status']);
            $table->index('cost_usd');
            $table->index('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glint_generations');
    }
};
