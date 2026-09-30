<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Item;
use App\Models\PriceList;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Support\Access;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Transaksi';

    protected static ?string $modelLabel = 'Transaction';

    protected static ?string $pluralModelLabel = 'Transaction';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $canViewFinancial = static::canViewFinancial();
        $itemTableColumns = [
            TableColumn::make('Barang')
                ->width($canViewFinancial ? '48%' : '80%')
                ->markAsRequired(),
        ];

        if ($canViewFinancial) {
            $itemTableColumns[] = TableColumn::make('Harga Satuan')
                ->width('18%')
                ->alignment(Alignment::End);
        }

        $itemTableColumns[] = TableColumn::make('Jumlah')
            ->width($canViewFinancial ? '12%' : '20%')
            ->alignment(Alignment::Center)
            ->markAsRequired();

        if ($canViewFinancial) {
            $itemTableColumns[] = TableColumn::make('Subtotal')
                ->width('18%')
                ->alignment(Alignment::End);
        }

        $itemSchema = [
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
                ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => static::syncTransactionItemFromItem($set, $get, $state, $canViewFinancial)),
            Hidden::make('item_name')
                ->required(),
        ];

        if ($canViewFinancial) {
            $itemSchema[] = Hidden::make('item_price')
                ->default(0)
                ->required();
            $itemSchema[] = Placeholder::make('item_price_preview')
                ->label('Harga Satuan')
                ->content(fn (Get $get): string => Money::rupiah($get('item_price')));
        }

        $itemSchema[] = TextInput::make('quantity')
            ->label('Jumlah')
            ->integer()
            ->minValue(1)
            ->default(1)
            ->live()
            ->placeholder('1')
            ->required()
            ->afterStateUpdated(fn (Set $set, Get $get, ?string $state) => static::syncTransactionItemSubtotal($set, $get, $state, $canViewFinancial));

        if ($canViewFinancial) {
            $itemSchema[] = Hidden::make('subtotal')
                ->default(0)
                ->required();
            $itemSchema[] = Placeholder::make('subtotal_preview')
                ->label('Subtotal')
                ->content(fn (Get $get): string => Money::rupiah($get('subtotal')));
        }

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
                            ->relationship(modifyQueryUsing: fn (Builder $query): Builder => $query->whereNull('price_list_id'))
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
                            ->table($itemTableColumns)
                            ->schema($itemSchema)
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data): array => static::normalizeTransactionItemData($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data): array => static::normalizeTransactionItemData($data))
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
                Section::make('Layanan Servis (Daftar Harga)')
                    ->description('Pilih layanan dari master Daftar Harga. Harganya masuk ke perhitungan dan bisa digabung dengan Biaya Servis, atau dipakai tanpa Biaya Servis.')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        Select::make('price_list_picks')
                            ->label('Layanan Terpilih')
                            ->placeholder('Cari dan pilih layanan dari daftar harga')
                            ->native(false)
                            ->multiple()
                            ->options(fn (): array => static::getPriceListOptions())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateHydrated(function (Select $component, ?Transaction $record): void {
                                $component->state(
                                    $record?->transactionItems()
                                        ->whereNotNull('price_list_id')
                                        ->pluck('price_list_id')
                                        ->all() ?? [],
                                );
                            })
                            ->columnSpanFull(),
                        TextInput::make('service_fee')
                            ->label('Biaya Servis')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('Rp')
                            ->live()
                            ->helperText('Nominal yang dibayarkan customer untuk jasa servis.')
                            ->required(),
                        Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Cash',
                                'qris' => 'QRIS',
                            ])
                            ->default('cash')
                            ->required()
                            ->native(false),
                        ...($canViewFinancial ? [
                            Placeholder::make('price_list_income_preview')
                                ->label('Total Layanan Terpilih')
                                ->content(fn (Get $get): string => Money::rupiah(static::sumPickedPriceListIncome($get('price_list_picks')))),
                        ] : []),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('User Terlibat & Komisi')
                    ->description('Hanya user yang dipilih di sini yang menerima komisi. Komisi dihitung dari Nilai Tagihan Bruto, bukan profit transaksi.')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Select::make('participant_ids')
                            ->label('User Terlibat')
                            ->placeholder('Pilih user yang terlibat dalam transaksi')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn (?Transaction $record): array => static::getParticipantOptions($record))
                            ->afterStateHydrated(function (Select $component, ?Transaction $record): void {
                                $component->state($record?->participants()->pluck('users.id')->all() ?? []);
                            })
                            ->live()
                            ->helperText('User baru yang dapat dipilih harus memiliki komisi aktif dan persentase di atas 0%.')
                            ->columnSpanFull(),
                        ...($canViewFinancial ? [
                            Placeholder::make('commission_preview')
                                ->label('Estimasi Komisi Peserta')
                                ->content(fn (Get $get): string => static::commissionPreview(
                                    $get('participant_ids'),
                                    (float) ($get('service_fee') ?: 0) + static::sumPickedPriceListIncome($get('price_list_picks')),
                                ))
                                ->columnSpanFull(),
                        ] : []),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $canViewFinancial = static::canViewFinancial();

        return $schema
            ->components([
                Section::make('Ringkasan Transaksi')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->schema([
                        TextEntry::make('transaction_number')
                            ->label('No. Transaksi'),
                        TextEntry::make('transaction_date')
                            ->label('Tanggal')
                            ->date('d M Y'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => $state === Transaction::STATUS_LOCKED ? 'Terkunci' : 'Draft')
                            ->color(fn (string $state): string => $state === Transaction::STATUS_LOCKED ? 'success' : 'warning'),
                        TextEntry::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'qris' => 'QRIS',
                                default => 'Cash',
                            }),
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
                    ])
                    ->columns(['md' => 2, 'xl' => 3])
                    ->columnSpanFull(),
                Grid::make(['lg' => 2])
                    ->schema([
                        Section::make('Barang Digunakan')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                TextEntry::make('items_summary')
                                    ->label('Daftar Barang')
                                    ->listWithLineBreaks()
                                    ->state(fn (Transaction $record): array => $record->transactionItems
                                        ->whereNull('price_list_id')
                                        ->map(fn (TransactionItem $item): string => $canViewFinancial
                                            ? "{$item->item_name} × {$item->quantity} — Modal ".Money::rupiah($item->item_price).', subtotal '.Money::rupiah($item->subtotal)
                                            : "{$item->item_name} × {$item->quantity}")
                                        ->values()
                                        ->all())
                                    ->placeholder('Belum ada barang yang digunakan.'),
                            ]),
                        Section::make('Layanan Servis')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                TextEntry::make('price_list_services')
                                    ->label('Layanan Terpilih')
                                    ->listWithLineBreaks()
                                    ->state(fn (Transaction $record): array => $record->transactionItems
                                        ->whereNotNull('price_list_id')
                                        ->map(fn (TransactionItem $item): string => $canViewFinancial
                                            ? "{$item->item_name} x{$item->quantity} — ".Money::rupiah($item->subtotal)
                                            : "{$item->item_name} x{$item->quantity}")
                                        ->values()->all())
                                    ->placeholder('Belum ada layanan daftar harga.'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('User Terlibat')
                    ->icon('heroicon-o-users')
                    ->schema([
                        TextEntry::make('participants_summary')
                            ->label('Peserta Transaksi')
                            ->listWithLineBreaks()
                            ->state(fn (Transaction $record): array => $record->participants->map(function (User $user) use ($record, $canViewFinancial): string {
                                if (! $canViewFinancial) {
                                    return $user->name;
                                }
                                $commission = $record->commissions->firstWhere('user_id', $user->id);
                                $suffix = $commission ? ' — '.$commission->percent.'% • '.Money::rupiah($commission->amount) : '';

                                return $user->name.$suffix;
                            })->all())
                            ->placeholder('Belum ada user yang terlibat.'),
                    ])
                    ->columnSpanFull(),
                Section::make('Ringkasan Keuangan')
                    ->icon('heroicon-o-banknotes')
                    ->visible($canViewFinancial)
                    ->schema([
                        TextEntry::make('total_income')
                            ->label('Pendapatan')
                            ->hintIcon('heroicon-o-information-circle', fn (Transaction $record): string => static::incomeSourceTooltip($record))
                            ->money('IDR', locale: 'id', decimalPlaces: 0),
                        TextEntry::make('total_item_cost')->label('Modal Barang')->money('IDR', locale: 'id', decimalPlaces: 0),
                        TextEntry::make('gross_profit')->label('Profit')->money('IDR', locale: 'id', decimalPlaces: 0),
                        TextEntry::make('total_commission')->label('Total Komisi Peserta')->money('IDR', locale: 'id', decimalPlaces: 0)
                            ->state(fn (Transaction $record): float => (float) $record->commissions->sum('amount')),
                    ])
                    ->columns(['md' => 2, 'xl' => 5])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $canViewFinancial = static::canViewFinancial();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'participants:id,name',
                'transactionItems:id,transaction_id,price_list_id,item_name,quantity,subtotal',
            ]))
            ->columns([
                TextColumn::make('transaction_number')
                    ->label('No. Transaksi')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transaction_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === Transaction::STATUS_LOCKED ? 'Terkunci' : 'Draft')
                    ->color(fn (string $state): string => $state === Transaction::STATUS_LOCKED ? 'success' : 'warning')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vehicle_name')
                    ->label('Kendaraan')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('participants_summary')
                    ->label('Pekerja')
                    ->state(fn (Transaction $record): string => $record->participants
                        ->pluck('name')
                        ->implode(', '))
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('services_summary')
                    ->label('Layanan')
                    ->state(fn (Transaction $record): string => $record->transactionItems
                        ->whereNotNull('price_list_id')
                        ->map(fn (TransactionItem $item): string => "{$item->item_name} × {$item->quantity}")
                        ->implode(', '))
                    ->placeholder('-')
                    ->wrap(),
                TextColumn::make('payment_method')
                    ->label('Pembayaran')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'qris' => 'QRIS',
                        default => 'Cash',
                    })
                    ->color(fn (?string $state): string => $state === 'qris' ? 'info' : 'success')
                    ->sortable(),
                TextColumn::make('service_fee')
                    ->label('Biaya Servis')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->visible($canViewFinancial)
                    ->sortable(),
                TextColumn::make('total_income')
                    ->label('Pendapatan')
                    ->icon('heroicon-o-information-circle')
                    ->iconColor('gray')
                    ->tooltip(fn (Transaction $record): string => static::incomeSourceTooltip($record))
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->visible($canViewFinancial)
                    ->sortable(),
                TextColumn::make('total_item_cost')
                    ->label('Total Modal')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->visible($canViewFinancial)
                    ->sortable(),
                TextColumn::make('gross_profit')
                    ->label('Profit')
                    ->money('IDR', locale: 'id', decimalPlaces: 0)
                    ->visible($canViewFinancial)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('transaction_date_range')
                    ->label('Tanggal Transaksi')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari tanggal'),
                        DatePicker::make('until')
                            ->label('Sampai tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, string $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    }),
                SelectFilter::make('status')
                    ->label('Status Transaksi')
                    ->options([
                        Transaction::STATUS_DRAFT => 'Draft',
                        Transaction::STATUS_LOCKED => 'Terkunci',
                    ]),
                SelectFilter::make('participant_id')
                    ->label('Pekerja')
                    ->options(fn (): array => User::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, int|string $userId): Builder => $query->whereHas(
                                'participants',
                                fn (Builder $participants): Builder => $participants->whereKey($userId),
                            ),
                        );
                    }),
                SelectFilter::make('price_list_id')
                    ->label('Layanan')
                    ->options(fn (): array => PriceList::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, int|string $priceListId): Builder => $query->whereHas(
                                'transactionItems',
                                fn (Builder $items): Builder => $items->where('price_list_id', $priceListId),
                            ),
                        );
                    }),
                SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'cash' => 'Cash',
                        'qris' => 'QRIS',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('print')
                    ->label('Print PDF')
                    ->icon('heroicon-o-printer')
                    ->iconButton()
                    ->tooltip('Print PDF')
                    ->url(fn (Transaction $record): string => route('transactions.print', $record))
                    ->openUrlInNewTab(),
                ViewAction::make()->iconButton()->tooltip('Lihat'),
                EditAction::make()->iconButton()->tooltip('Ubah'),
                DeleteAction::make()->iconButton()->tooltip('Hapus'),
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

    public static function canViewFinancial(): bool
    {
        return auth()->user()?->can(Access::TRANSACTIONS_VIEW_FINANCIAL) ?? false;
    }

    public static function incomeSourceTooltip(Transaction $record): string
    {
        $serviceFee = (float) $record->service_fee;
        $priceListIncome = (float) $record->transactionItems
            ->whereNotNull('price_list_id')
            ->sum('subtotal');

        if ($serviceFee > 0 && $priceListIncome > 0) {
            return 'Pendapatan dari Biaya Servis '.Money::rupiah($serviceFee)
                .' dan Daftar Harga '.Money::rupiah($priceListIncome).'.';
        }

        if ($serviceFee > 0) {
            return 'Pendapatan seluruhnya dari Biaya Servis '.Money::rupiah($serviceFee).'.';
        }

        if ($priceListIncome > 0) {
            return 'Pendapatan seluruhnya dari Daftar Harga '.Money::rupiah($priceListIncome).'.';
        }

        return 'Belum ada Biaya Servis atau layanan Daftar Harga pada transaksi ini.';
    }

    public static function lockAction(): Action
    {
        return Action::make('lock')
            ->label('Kunci')
            ->icon('heroicon-o-lock-closed')
            ->color('success')
            ->authorize('lock')
            ->visible(fn (Transaction $record): bool => $record->isDraft())
            ->requiresConfirmation()
            ->modalHeading('Kunci transaksi?')
            ->modalDescription('Total dan komisi akan dikunci sebagai histori.')
            ->action(fn (Transaction $record): mixed => $record->lock());
    }

    public static function customCommissionAction(): Action
    {
        return Action::make('customCommission')
            ->label('Atur Komisi Custom')
            ->icon('heroicon-o-adjustments-horizontal')
            ->color('gray')
            ->authorize('update')
            ->visible(fn (Transaction $record): bool => $record->isDraft() && static::canViewFinancial())
            ->modalHeading('Atur Komisi Khusus Transaksi')
            ->modalDescription('Pilih peserta lalu isi persen khusus. Kosongkan persen untuk kembali memakai pengaturan user.')
            ->schema(function (Action $action): array {
                /** @var Transaction $record */
                $record = $action->getRecord();
                $commissions = $record->commissions->keyBy('user_id');

                return [
                    Select::make('user_id')
                        ->label('User Terlibat')
                        ->options($record->participants->mapWithKeys(function (User $user) use ($commissions): array {
                            $percent = $commissions->get($user->id)?->percent ?? $user->commission_percent;

                            return [$user->id => "{$user->name} ({$percent}%)"];
                        })->all())
                        ->required()
                        ->searchable(),
                    TextInput::make('custom_percent')
                        ->label('Persen Custom')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->suffix('%')
                        ->nullable(),
                ];
            })
            ->action(function (array $data, Transaction $record): void {
                $record->applyCustomCommissionPercents([(int) $data['user_id'] => $data['custom_percent'] ?? null]);
                $record->syncDraftCommissionSnapshots();
            });
    }

    protected static function syncTransactionItemFromItem(Set $set, Get $get, ?string $state, bool $canViewFinancial): void
    {
        $item = filled($state) ? Item::query()->find($state) : null;

        $set('item_name', $item?->name ?? '');

        if ($canViewFinancial) {
            $price = (float) ($item?->price ?? 0);
            $quantity = max(1, (int) ($get('quantity') ?: 1));

            $set('item_price', $price);
            $set('subtotal', $price * $quantity);
        }
    }

    protected static function syncTransactionItemSubtotal(Set $set, Get $get, mixed $state, bool $canViewFinancial): void
    {
        $quantity = max(1, (int) ($state ?: 1));

        $set('quantity', $quantity);

        if ($canViewFinancial) {
            $price = (float) ($get('item_price') ?: 0);

            $set('subtotal', $price * $quantity);
        }
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
            ? ' - '.str($item->description)->limit(40)->toString()
            : '';

        return "{$item->name} ({$priceLabel}){$description}";
    }

    /**
     * Pilihan layanan daftar harga mentah dari form, dinormalisasi menjadi
     * daftar id unik.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    public static function extractPriceListPicks(array $data): array
    {
        return array_values(array_unique(array_map(
            intval(...),
            array_filter((array) ($data['price_list_picks'] ?? [])),
        )));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, int>
     */
    public static function extractParticipantIds(array $data): array
    {
        return array_values(array_unique(array_map(
            intval(...),
            array_filter((array) ($data['participant_ids'] ?? [])),
        )));
    }

    /**
     * @return array<int, string>
     */
    protected static function getParticipantOptions(?Transaction $record): array
    {
        $existingIds = $record?->participants()->pluck('users.id')->all() ?? [];

        return User::query()
            ->where(function (Builder $query) use ($existingIds): void {
                $query->where(function (Builder $query): void {
                    $query->where('commission_percent', '>', 0);
                })->orWhereIn('id', $existingIds);
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [$user->id => "{$user->name} ({$user->commission_percent}%)"])
            ->all();
    }

    protected static function commissionPreview(mixed $participantIds, float $grossAmount): string
    {
        $participants = User::query()
            ->whereIn('id', array_filter((array) ($participantIds ?? [])))
            ->where('commission_percent', '>', 0)
            ->get();

        if ($participants->isEmpty()) {
            return filled($participantIds)
                ? 'Tidak ada peserta dengan persentase komisi di atas 0%.'
                : 'Belum ada user yang dipilih. Tidak ada komisi yang akan dibuat.';
        }

        $details = $participants->map(fn (User $user): string => sprintf(
            '%s %s%% = %s',
            $user->name,
            $user->commission_percent,
            Money::rupiah(round($grossAmount * (float) $user->commission_percent / 100, 2)),
        ));
        $total = (float) $participants->sum(
            fn (User $user): float => round($grossAmount * (float) $user->commission_percent / 100, 2),
        );

        return 'Nilai Tagihan Bruto '.Money::rupiah($grossAmount)
            .' — '.$details->implode(', ')
            .'. Total komisi peserta: '.Money::rupiah($total).'.';
    }

    protected static function sumPickedPriceListIncome(mixed $picks): float
    {
        return (float) PriceList::query()
            ->whereIn('id', array_filter((array) ($picks ?? [])))
            ->sum('price');
    }

    /**
     * @return array<int, string>
     */
    protected static function getPriceListOptions(): array
    {
        return PriceList::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (PriceList $priceList): array => [$priceList->id => static::formatPriceListOptionLabel($priceList)])
            ->all();
    }

    protected static function formatPriceListOptionLabel(PriceList $priceList): string
    {
        $priceLabel = Money::rupiah($priceList->price ?? 0);
        $description = filled($priceList->description)
            ? ' - '.str($priceList->description)->limit(40)->toString()
            : '';

        return "{$priceList->name} ({$priceLabel}){$description}";
    }

    /**
     * Sinkronkan baris layanan daftar harga (transaction_items yang punya
     * price_list_id) pada sebuah transaksi dengan pilihan layanan di form.
     * Baris barang (price_list_id kosong) tidak pernah disentuh di sini.
     *
     * @param  array<int, int>  $picks
     */
    public static function syncPriceListServices(Transaction $record, array $picks): void
    {
        $picks = static::extractPriceListPicks(['price_list_picks' => $picks]);

        $existingLayanan = $record->transactionItems()
            ->whereNotNull('price_list_id')
            ->pluck('price_list_id')
            ->map(fn ($id): int => (int) $id);

        $record->transactionItems()
            ->whereNotNull('price_list_id')
            ->whereNotIn('price_list_id', $picks)
            ->delete();

        $kept = $existingLayanan->intersect($picks);

        foreach ($picks as $priceListId) {
            if ($kept->contains((int) $priceListId)) {
                continue;
            }

            $priceList = PriceList::query()->find($priceListId);

            if ($priceList === null) {
                continue;
            }

            $record->transactionItems()->create([
                'price_list_id' => $priceList->id,
                'item_name' => $priceList->name,
                'item_price' => $priceList->price,
                'quantity' => 1,
            ]);
        }

        // Penghapusan massal di atas tidak memicu model events (recalculateTotals),
        // jadi hitung ulang totals secara eksplisit di akhir sinkronisasi.
        $record->refresh();
        $record->recalculateTotals();
    }
}
