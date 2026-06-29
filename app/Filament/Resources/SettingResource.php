<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Setting';

    protected static ?string $modelLabel = 'Setting';

    protected static ?string $pluralModelLabel = 'Setting';

    protected static ?int $navigationSort = 5;

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        TextInput::make('shop_name')
                            ->label('Nama Bengkel')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone_number')
                            ->label('Nomor Telepon')
                            ->tel()
                            ->maxLength(255),
                        FileUpload::make('logo')
                            ->label('Logo')
                            ->disk('public')
                            ->directory('settings')
                            ->visibility('public')
                            ->image()
                            ->imageEditor()
                            ->openable()
                            ->downloadable(),
                        Textarea::make('address')
                            ->label('Alamat')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function getPages(): array
    {
        return [
            'index' => Pages\RedirectSetting::route('/'),
            'edit' => Pages\EditSetting::route('/{record}/edit'),
        ];
    }
}
