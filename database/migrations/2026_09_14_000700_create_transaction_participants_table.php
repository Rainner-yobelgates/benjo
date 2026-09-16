<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transaction_id', 'user_id']);
        });

        DB::table('transaction_commissions')
            ->select(['transaction_id', 'user_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->each(function (object $commission): void {
                DB::table('transaction_participants')->insertOrIgnore([
                    'transaction_id' => $commission->transaction_id,
                    'user_id' => $commission->user_id,
                    'created_at' => $commission->created_at ?? now(),
                    'updated_at' => $commission->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_participants');
    }
};
