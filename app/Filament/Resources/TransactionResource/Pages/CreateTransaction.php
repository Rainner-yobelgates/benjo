<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $transactionDate = now();

        $data['transaction_date'] = $transactionDate->toDateString();
        $data['transaction_number'] = Transaction::generateTransactionNumber($transactionDate);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->refresh();
        $this->record->recalculateTotals();
    }
}
