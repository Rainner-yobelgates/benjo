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
            Action::make('print')
                ->label('Print PDF')
                ->icon(Heroicon::OutlinedPrinter)
                ->url(route('transactions.print', $this->record))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
