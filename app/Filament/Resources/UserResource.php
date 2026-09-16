<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'User';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Data User')
                    ->schema([
                        TextInput::make('name')
                            ->label('Username')
                            ->required()
                            ->maxLength(255)
                            ->unique('users', 'name', ignoreRecord: true),
                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->helperText('Ditentukan saat user baru dibuat. Untuk mengubah password, hapus dan buat ulang user ini.')
                            ->hiddenOn('edit')
                            ->required(),
                        Select::make('role_id')
                            ->label('Role')
                            ->options(fn (): array => Role::query()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->afterStateHydrated(function (Select $component, ?User $record): void {
                                $component->state($record?->roles()->value('id'));
                            })
                            ->searchable()
                            ->preload()
                            ->required(),
                        Toggle::make('commission_active')
                            ->label('Komisi Aktif')
                            ->helperText('Aktifkan agar user ini menerima komisi dari setiap transaksi.')
                            ->live(),
                        TextInput::make('commission_percent')
                            ->label('Persen Komisi')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->helperText('Persentase komisi, dibagi dari total pemasukan transaksi.')
                            ->default(0)
                            ->dehydrated(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Username')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('commission_percent')
                    ->label('Komisi')
                    ->formatStateUsing(fn ($state, User $record): string => $record->hasActiveCommission()
                        ? "{$state}% (Aktif)"
                        : (($state === null) ? '-' : "{$state}% (Nonaktif)"))
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function syncRole(User $user, int|string $roleId): void
    {
        $role = Role::query()->findOrFail($roleId);

        $user->syncRoles([$role]);
    }
}
