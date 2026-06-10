<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glint_spans', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('trace_id');
            $table->ulid('parent_span_id')->nullable();
            $table->string('name');
            $table->string('type', 20)->default('span');
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->json('metadata')->nullable();
            $table->string('status', 10)->default('pending');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('trace_id');
            $table->index('parent_span_id');
            $table->index('type');
            $table->index('status');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glint_spans');
    }
};
