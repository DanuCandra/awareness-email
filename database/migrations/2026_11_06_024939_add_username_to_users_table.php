<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable()->unique();
        });

        DB::table('users')->orderBy('id')->chunkById(100, function (Collection $users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update(['username' => 'admin-'.$user->id]);
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('username', 30)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
