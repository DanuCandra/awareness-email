<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('simulation_clicks')) {
            Schema::create('simulation_clicks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('simulation_recipient_id')->constrained()->cascadeOnDelete();
                $table->string('ip', 45)->nullable();
                $table->string('user_agent', 1000)->nullable();
                $table->timestamp('clicked_at');
                $table->string('period', 10);
            });
        }
        if (! Schema::hasIndex('simulation_clicks', 'click_recipient_period_time')) {
            Schema::table('simulation_clicks', function (Blueprint $table): void {
                $table->index(['simulation_recipient_id', 'period', 'clicked_at'], 'click_recipient_period_time');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_clicks');
    }
};
