<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Item;
use App\Models\Transaction;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn as InfolistTableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Transaction';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transaction';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data Customer & Kendaraan')
                    ->description('Isi identitas customer dan informasi kendaraan yang sedang dikerjakan.')
                    ->icon('heroicon-o-user')
                    ->schema([
                        TextInput::make('customer_name')
                            ->label('Nama Customer')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('customer_phone')
                            ->label('No. HP Customer')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('vehicle_name')
                            ->label('Nama Kendaraan')
                            ->maxLength(255),
                        Textarea::make('service_description')
                            ->label('Deskripsi Servis')
                            ->placeholder('Contoh: Ganti oli dan servis rem depan')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('Barang yang Digunakan')
                    ->description('Pilih barang dari master Barang dan isi jumlah pemakaiannya. Harga dan subtotal dihitung otomatis oleh sistem.')
                    ->icon('heroicon-o-cube')
                    ->schema([
                        Repeater::make('transactionItems')
                            ->label('Daftar Barang')
                            ->relationship()
                            ->defaultItems(1)
                            ->minItems(1)
                            ->addActionLabel('Tambah Barang')
                            ->reorderable(false)
                            ->deleteAction(fn (Action $action): Action => $action->tooltip('Hapus barang'))
                            ->itemLabel(function (array $state): ?string {
                                if (blank($state['item_name'] ?? null)) {
                                    return 'Barang belum dipilih';
                                }

                                $quantity = max(1, (int) ($state['quantity'] ?? 1));

                                return "{$state['item_name']} x{$quantity}";
                            })
                            ->table([
                                TableColumn::make('Barang')
                                    ->width('48%')
                                    ->markAsRequired(),
                                TableColumn::make('Harga Satuan')
                                    ->width('18%')
                                    ->alignment(Alignment::End),
                                TableColumn::make('Jumlah')
                                    ->width('12%')
                                    ->alignment(Alignment::Center)
                                    ->markAsRequired(),
                                TableColumn::make('Subtotal')
                                    ->width('18%')
                                    ->alignment(Alignment::End),
                            ])
                            ->schema([
                                Select::make('item_id')
                                    ->label('Barang')
                                    ->placeholder('Cari dan pilih barang')
                                    ->native(false)
                                    ->options(fn (): array => static::getItemSelectOptions())
                                    ->searchable()
                                    ->preload()
                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                    ->live()
                                    ->required()
                                    ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => static::syncTransactionItemFromItem($set, $get, $state)),
                                Hidden::make('item_name')
                                    ->required(),
                                Hidden::make('item_price')
                                    ->default(0)
                                    ->required(),
                                Placeholder::make('item_price_preview')
                                    ->label('Harga Satuan')
                                    ->content(fn (Get $get): string => Money::rupiah($get('item_price'))),
                                TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->live()
                                    ->placeholder('1')
                                    ->required()
                                    ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => static::syncTransactionItemSubtotal($set, $get, $state)),
                                Hidden::make('subtotal')
                                    ->default(0)
                                    ->required(),
                                Placeholder::make('subtotal_preview')
                                    ->label('Subtotal')
                                    ->content(fn (Get $get): string => Money::rupiah($get('subtotal'))),
                            ])
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => static::normalizeTransactionItemData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => static::normalizeTransactionItemData($data))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Biaya & Ringkasan')
                    ->description('Biaya servis adalah pemasukan dari customer. Pengeluaran barang dihitung dari seluruh barang di atas.')
                    ->icon('heroicon-o-calculator')
                    ->schema([
                        TextInput::make('service_fee')
                            ->label('Biaya Servis')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('Rp')
                            ->live()
                            ->helperText('Nominal yang dibayarkan customer untuk jasa servis.')
                            ->required(),
                        Placeholder::make('total_item_cost_preview')
                            ->label('Total Pengeluaran Barang')
                            ->content(fn (Get $get): string => Money::rupiah(static::sumTransactionItemSubtotals($get('transactionItems')))),
                        Placeholder::make('gross_profit_preview')
                            ->label('Estimasi Profit Transaksi')
                            ->content(fn (Get $get): string => Money::rupiah(
                                (float) ($get('service_fee') ?: 0) - static::sumTransactionItemSubtotals($get('transactionItems')),
                            )),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Transaksi')
                    ->schema([
                        TextEntry::make('transaction_number')
                            ->label('No. Transaksi'),
                        TextEntry::make('transaction_date')
                            ->label('Tanggal')
                            ->date('d M Y'),
                        TextEntry::make('customer_name')
                            ->label('Nama Customer'),
                        TextEntry::make('customer_phone')
                            ->label('No. HP Customer')
                            ->placeholder('-'),
                        TextEntry::make('vehicle_name')
                            ->label('Nama Kendaraan')
                            ->placeholder('-'),
                        TextEntry::make('service_description')
                            ->label('Deskripsi Servis')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('service_fee')
                            ->label('Biaya Servis')
                            ->money('IDR', locale: 'id', decimalPlaces: 0),
                        TextEntry::make('total_item_cost')
                            ->label('Total Modal Barang')
                            ->money('IDR', locale: 'id', decimalPlaces: 0),
                        TextEntry::make('gross_profit')
                            ->label('Gross Profit')
                            ->money('IDR', locale: 'id', decimalPlaces: 0),
                    ])
                    ->columns(2),
                Section::make('Barang Digunakan')
                    ->schema([
                        RepeatableEntry::make('transactionItems')
                            ->label('')
                            ->table([
                                InfolistTableColumn::make('Barang'),
                                InfolistTableColumn::make('Harga Modal')->alignment(Alignment::End),
                                InfolistTableColumn::make('Qty')->alignment(Alignment::Center),
                                InfolistTableColumn::make('Subtotal')->alignment(Alignment::End),
                            ])
                            ->schema([
                                TextEntry::make('item_name')
                                    ->label('Barang'),
                                TextEntry::make('item_price')
                                    ->label('Harga Modal')
                                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                                TextEntry::make('quantity')
                                    ->label('Qty'),
                                TextEntry::make('subtotal')
                                    ->label('Subtotal')
                                    ->money('IDR', locale: 'id', decimalPlaces: 0),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')
                    ->label('No. Transaksi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle_name')
                    ->label('Kendaraan')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('service_fee')
                    ->label('Biaya Servis')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('total_item_cost')
                    ->label('Total Modal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('gross_profit')
                    ->label('Gross Profit')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('print')
                    ->label('Print PDF')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Transaction $record): string => route('transactions.print', $record))
                    ->openUrlInNewTab(),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'view' => Pages\ViewTransaction::route('/{record}'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }

    protected static function syncTransactionItemFromItem(Set $set, Get $get, ?string $state): void
    {
        $item = filled($state) ? Item::query()->find($state) : null;
        $price = (float) ($item?->price ?? 0);
        $quantity = max(1, (int) ($get('quantity') ?: 1));

        $set('item_name', $item?->name ?? '');
        $set('item_price', $price);
        $set('subtotal', $price * $quantity);
    }

    protected static function syncTransactionItemSubtotal(Set $set, Get $get, mixed $state): void
    {
        $quantity = max(1, (int) ($state ?: 1));
        $price = (float) ($get('item_price') ?: 0);

        $set('quantity', $quantity);
        $set('subtotal', $price * $quantity);
    }

    protected static function sumTransactionItemSubtotals(mixed $items): float
    {
        return (float) collect(is_array($items) ? $items : [])->sum(
            fn (array $item): float => (float) ($item['subtotal'] ?? 0),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected static function normalizeTransactionItemData(array $data): array
    {
        $item = filled($data['item_id'] ?? null)
            ? Item::query()->find($data['item_id'])
            : null;

        $price = (float) ($item?->price ?? $data['item_price'] ?? 0);
        $quantity = max(1, (int) ($data['quantity'] ?? 1));

        $data['item_name'] = $item?->name ?? ($data['item_name'] ?? '-');
        $data['item_price'] = $price;
        $data['quantity'] = $quantity;
        $data['subtotal'] = $price * $quantity;

        return $data;
    }

    /**
     * @return array<int, string>
     */
    protected static function getItemSelectOptions(): array
    {
        return Item::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Item $item): array => [$item->id => static::formatItemOptionLabel($item)])
            ->all();
    }

    protected static function formatItemOptionLabel(Item $item): string
    {
        $priceLabel = Money::rupiah($item->price ?? 0);
        $description = filled($item->description)
            ? ' - ' . str($item->description)->limit(40)->toString()
            : '';

        return "{$item->name} ({$priceLabel}){$description}";
    }
}
