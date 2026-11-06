<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('simulations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_token')->nullable()->unique();
            $table->uuid('dispatch_version');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('sender_name', 80);
            $table->string('sender_email', 254)->default('');
            $table->string('reply_to', 254)->default('');
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->string('subject', 180)->default('');
            $table->json('delta');
            $table->string('recipient_mode', 10);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->unsignedInteger('send_interval_seconds')->default(0);
            $table->string('last_error', 300)->nullable();
            $table->timestamps();
            $table->index(['status', 'starts_at']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulations');
    }
};
