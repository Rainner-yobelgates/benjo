<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTransaction extends ViewRecord
{
    protected static string $resource = TransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TransactionResource::customCommissionAction(),
            TransactionResource::lockAction(),
            Action::make('unlock')
                ->label('Buka Kunci')
                ->icon(Heroicon::OutlinedLockOpen)
                ->color('warning')
                ->authorize('unlock')
                ->visible(fn (): bool => $this->record->isLocked())
                ->requiresConfirmation()
                ->modalHeading('Buka kunci transaksi?')
                ->modalDescription('Transaksi akan kembali menjadi Draft. Komisi akan mengikuti total dan persentase user terbaru.')
                ->action(function (): void {
                    $this->record->unlock();
                    $this->redirect(TransactionResource::getUrl('edit', ['record' => $this->record]));
                }),
            Action::make('print')
                ->label('Print PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(route('transactions.print', $this->record))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
