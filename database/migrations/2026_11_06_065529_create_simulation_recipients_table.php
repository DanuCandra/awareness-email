<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulation_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('simulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email', 254);
            $table->string('token', 64)->unique();
            $table->string('status', 20)->default('pending');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('sender_email', 254)->nullable();
            $table->string('error', 300)->nullable();
            $table->timestamps();
            $table->unique(['simulation_id', 'email']);
            $table->index(['simulation_id', 'status', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_recipients');
    }
};
