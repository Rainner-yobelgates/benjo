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
     * Simpan transaksi utama, barang, layanan, total, dan komisi dalam satu
     * database transaction. Bila salah satu tahap gagal, tidak ada transaksi
     * dengan total sementara yang tersisa.
     */
    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    /**
     * Layanan daftar harga yang dipilih di form, dipindahkan ke properti
     * ini lalu disinkronkan sebagai transaction_items setelah transaksi
     * dibuat (kolom price_list_id tidak ada di tabel transactions).
     *
     * @var  array<int, int>
     */
    protected array $priceListPicks = [];

    /**
     * @var array<int, int>
     */
    protected array $participantIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->priceListPicks = TransactionResource::extractPriceListPicks($data);
        $this->participantIds = TransactionResource::extractParticipantIds($data);
        unset($data['price_list_picks'], $data['participant_ids']);

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
        $this->record->syncCommissionParticipants($this->participantIds);
    }
}
