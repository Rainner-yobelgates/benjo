<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TransactionPrintController extends Controller
{
    public function __invoke(Transaction $transaction): View
    {
        Gate::forUser(Auth::user())->authorize('view', [$transaction]);

        $transaction->load('transactionItems.item');

        return view('transactions.print', [
            'transaction' => $transaction,
            'setting' => Setting::current(),
        ]);
    }
}
