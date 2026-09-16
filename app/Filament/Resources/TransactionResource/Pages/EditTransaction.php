<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    /**
     * Perubahan transaksi, item, layanan, total, dan peserta harus berhasil
     * bersama-sama atau seluruh perubahan dibatalkan.
     */
    public function hasDatabaseTransactions(): bool
    {
        return true;
    }

    /**
     * Layanan daftar harga yang dipilih di form (lihat CreateTransaction).
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
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->priceListPicks = TransactionResource::extractPriceListPicks($data);
        $this->participantIds = TransactionResource::extractParticipantIds($data);
        unset($data['price_list_picks'], $data['participant_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        TransactionResource::syncPriceListServices($this->record, $this->priceListPicks);

        $this->record->refresh();
        $this->record->recalculateTotals();
        $this->record->syncCommissionParticipants($this->participantIds);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('lock')
                ->label('Selesaikan & Kunci')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('success')
                ->authorize('lock')
                ->visible(fn (): bool => $this->record->isDraft())
                ->requiresConfirmation()
                ->modalHeading('Selesaikan dan kunci transaksi?')
                ->modalDescription('Total dan komisi akan dikunci sebagai histori dan tidak dapat diubah melalui form biasa.')
                ->action(function (): void {
                    $this->record->lock();
                    $this->redirect(TransactionResource::getUrl('view', ['record' => $this->record]));
                }),
            Action::make('print')
                ->label('Print PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(route('transactions.print', $this->record))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
