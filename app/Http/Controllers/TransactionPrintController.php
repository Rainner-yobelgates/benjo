<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;

class TransactionPrintController extends Controller
{
    public function __invoke(Transaction $transaction): View
    {
        $transaction->load('transactionItems.item');

        return view('transactions.print', [
            'transaction' => $transaction,
            'setting' => Setting::current(),
        ]);
    }
}
