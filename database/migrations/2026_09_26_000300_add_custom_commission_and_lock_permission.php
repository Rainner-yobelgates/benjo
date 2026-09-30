<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_commissions', function (Blueprint $table): void {
            $table->decimal('custom_percent', 5, 2)->nullable()->after('percent');
        });

        DB::table('permissions')->where('name', 'transactions.unlock')->update(['name' => 'transactions.lock']);
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'transactions.lock')->update(['name' => 'transactions.unlock']);

        Schema::table('transaction_commissions', function (Blueprint $table): void {
            $table->dropColumn('custom_percent');
        });
    }
};
