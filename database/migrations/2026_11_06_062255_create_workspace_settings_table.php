<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('section', 20)->unique();
            $table->json('values');
            $table->text('secret')->nullable();
            $table->uuid('revision');
            $table->timestamp('connection_checked_at')->nullable();
            $table->boolean('connection_successful')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_settings');
    }
};
