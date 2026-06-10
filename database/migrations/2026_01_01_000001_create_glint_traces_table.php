<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glint_traces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('user_id', 255)->nullable();
            $table->string('session_id', 255)->nullable();
            $table->string('team_id', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->longText('input')->nullable();
            $table->longText('output')->nullable();
            $table->string('status', 10)->default('pending');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('team_id');
            $table->index('status');
            $table->index('started_at');
            $table->index(['started_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glint_traces');
    }
};
