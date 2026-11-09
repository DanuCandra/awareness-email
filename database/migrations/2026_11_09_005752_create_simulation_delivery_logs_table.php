<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_delivery_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('simulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('simulation_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 254)->nullable();
            $table->string('event', 40);
            $table->string('message', 500);
            $table->string('event_key', 64)->nullable()->unique('delivery_log_event_unique');
            $table->timestamp('occurred_at');
            $table->index(['simulation_id', 'id'], 'delivery_log_simulation_cursor');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_delivery_logs');
    }
};
