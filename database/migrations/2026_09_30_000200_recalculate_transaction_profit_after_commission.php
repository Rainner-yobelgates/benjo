<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('transactions')
            ->select(['id', 'total_income', 'total_item_cost'])
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $transaction): void {
                $commission = (float) DB::table('transaction_commissions')
                    ->where('transaction_id', $transaction->id)
                    ->sum('amount');

                DB::table('transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'gross_profit' => (float) $transaction->total_income
                            - (float) $transaction->total_item_cost
                            - $commission,
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('transactions')
            ->update([
                'gross_profit' => DB::raw('total_income - total_item_cost'),
            ]);
    }
};
