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
     * Layanan daftar harga yang dipilih di form, dipindahkan ke properti
     * ini lalu disinkronkan sebagai transaction_items setelah transaksi
     * dibuat (kolom price_list_id tidak ada di tabel transactions).
     *
     * @var  array<int, int>
     */
    protected array $priceListPicks = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->priceListPicks = TransactionResource::extractPriceListPicks($data);
        unset($data['price_list_picks']);

        $transactionDate = now();

        $data['transaction_date'] = $transactionDate->toDateString();
        $data['transaction_number'] = Transaction::generateTransactionNumber($transactionDate);

        return $data;
    }

    protected function afterCreate(): void
    {
        TransactionResource::syncPriceListServices($this->record, $this->priceListPicks);

        $this->record->refresh();
        $this->record->recalculateTotals();
    }
}
