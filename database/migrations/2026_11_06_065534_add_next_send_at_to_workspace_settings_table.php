<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_settings', function (Blueprint $table): void {
            $table->timestamp('next_send_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('workspace_settings', function (Blueprint $table): void {
            $table->dropColumn('next_send_at');
        });
    }
};
