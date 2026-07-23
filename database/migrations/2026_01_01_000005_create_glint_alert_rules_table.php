<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('glint_alert_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20);
            $table->string('scope', 20)->default('global');
            $table->string('scope_id', 255)->nullable();
            $table->json('threshold_config');
            $table->json('channels');
            $table->string('webhook_url', 500)->nullable();
            $table->string('mail_to', 255)->nullable();
            $table->unsignedInteger('cooldown_minutes')->default(60);
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index('enabled');           // used in AlertDispatcher::evaluate() WHERE enabled=true
            $table->index('type');              // used in evaluateRule() match()
            $table->index(['scope', 'scope_id']); // used for scoped alert lookups
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('glint_alert_rules');
    }
};
