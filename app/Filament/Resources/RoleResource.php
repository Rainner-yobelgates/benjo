<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use App\Support\Access;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Role & Izin';

    protected static ?string $modelLabel = 'Role';

    protected static ?string $pluralModelLabel = 'Role';

    protected static ?int $navigationSort = 91;

    public static function canDelete(Model $record): bool
    {
        return $record instanceof Role && $record->name !== Access::MASTER_ROLE;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Data Role')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama Role')
                        ->required()
                        ->maxLength(255)
                        ->unique('roles', 'name', ignoreRecord: true)
                        ->helperText('Role "master" selamanya memiliki semua akses dan tidak bisa diubah atau dihapus.')
                        ->disabled(fn (string $operation, ?Role $record): bool => $operation === 'edit' && $record?->name === Access::MASTER_ROLE),
                ])
                ->columns(1)
                ->columnSpanFull(),

            Section::make('Izin Akses')
                ->description('Pilih izin untuk setiap module. Role "master" tidak bisa diedit dan tetap memiliki semua izin secara otomatis.')
                ->schema(self::permissionMatrixSchema())
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    private static function permissionMatrixSchema(): array
    {
        $isEdit = request()->route('record') !== null;
        $record = $isEdit ? Role::find(request()->route('record')) : null;

        if ($record?->name === Access::MASTER_ROLE) {
            return self::masterRoleSchema();
        }

        $grouped = Access::allGrouped();
        $schema = [];

        foreach ($grouped as $moduleKey => $module) {
            $actions = array_keys($module['actions']);
            $totalCount = count($module['actions']);

            $schema[] = Section::make($module['label'])
                ->description(fn (Get $get): string => self::moduleSummaryDescription(
                    self::activePermissionCount($get, $moduleKey, $actions, $record),
                    $totalCount,
                ))
                ->schema([
                    Toggle::make("permission_matrix.{$moduleKey}.all")
                        ->label('Aktifkan semua izin')
                        ->helperText('Nyalakan atau matikan seluruh izin pada modul ini.')
                        ->live()
                        ->afterStateHydrated(function (Toggle $component, ?Role $record) use ($moduleKey, $actions): void {
                            $component->state(self::roleHasAllModulePermissions($record, $moduleKey, $actions));
                        })
                        ->afterStateUpdated(function (Set $set, bool $state) use ($moduleKey, $actions): void {
                            foreach ($actions as $action) {
                                $set("permission_matrix.{$moduleKey}.{$action}", $state, isAbsolute: true);
                            }
                        })
                        ->onColor('success')
                        ->columnSpanFull(),
                    ...collect($module['actions'])
                        ->map(function (string $label, string $action) use ($moduleKey, $actions): Toggle {
                            return Toggle::make("permission_matrix.{$moduleKey}.{$action}")
                                ->label($label)
                                ->live()
                                ->afterStateHydrated(function (Toggle $component, ?Role $record) use ($moduleKey, $action): void {
                                    $component->state(self::roleHasPermission($record, "{$moduleKey}.{$action}"));
                                })
                                ->afterStateUpdated(function (Set $set, Get $get) use ($moduleKey, $actions): void {
                                    $set(
                                        "permission_matrix.{$moduleKey}.all",
                                        self::activePermissionCount($get, $moduleKey, $actions) === count($actions),
                                        isAbsolute: true,
                                    );
                                })
                                ->onColor('success');
                        })
                        ->all(),
                ])
                ->collapsible()
                ->collapsed(
                    $isEdit
                        ? ! self::roleHasAnyModulePermission($record, $moduleKey, $actions)
                        : ! in_array($moduleKey, ['dashboard', 'transactions'], true),
                )
                ->columns(3)
                ->columnSpanFull();
        }

        return $schema;
    }

    private static function masterRoleSchema(): array
    {
        return [
            Section::make('Role Master')
                ->description('Role Master secara otomatis memiliki seluruh permission. Permission untuk role ini tidak dapat diubah.')
                ->schema([
                    Placeholder::make('permissions')
                        ->label('Akses Master')
                        ->content('Role master selalu memiliki seluruh permission dan tidak dapat diubah atau dihapus.'),
                ])
                ->columns(1),
        ];
    }

    private static function moduleSummaryDescription(int $active, int $total): string
    {
        if ($total === 0) {
            return '';
        }

        if ($active === $total) {
            return "{$active} / {$total} aktif";
        }

        if ($active === 0) {
            return 'Tidak ada akses';
        }

        return "{$active} / {$total} aktif";
    }

    /**
     * Extract selected permission slugs from the non-persistent toggle state.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     */
    public static function extractSelectedPermissions(array $data): array
    {
        $matrix = (array) ($data['permission_matrix'] ?? []);
        $selected = [];

        foreach (Access::allGrouped() as $moduleKey => $module) {
            foreach (array_keys($module['actions']) as $action) {
                if (data_get($matrix, "{$moduleKey}.{$action}") === true) {
                    $selected[] = "{$moduleKey}.{$action}";
                }
            }
        }

        return $selected;
    }

    /**
     * @param  array<int, string>  $permissions
     */
    public static function syncPermissions(Role $role, array $permissions): void
    {
        $permissionModels = Permission::query()
            ->where('guard_name', $role->guard_name)
            ->whereIn('name', $permissions)
            ->get();

        $role->syncPermissions($permissionModels->all());
    }

    /**
     * @param  array<int, string>  $actions
     */
    private static function activePermissionCount(Get $get, string $moduleKey, array $actions, ?Role $record = null): int
    {
        return count(array_filter(
            $actions,
            function (string $action) use ($get, $moduleKey, $record): bool {
                $state = $get("permission_matrix.{$moduleKey}.{$action}", isAbsolute: true);

                return $state === null
                    ? self::roleHasPermission($record, "{$moduleKey}.{$action}")
                    : (bool) $state;
            },
        ));
    }

    /**
     * @param  array<int, string>  $actions
     */
    private static function roleHasAnyModulePermission(?Role $record, string $moduleKey, array $actions): bool
    {
        foreach ($actions as $action) {
            if (self::roleHasPermission($record, "{$moduleKey}.{$action}")) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $actions
     */
    private static function roleHasAllModulePermissions(?Role $record, string $moduleKey, array $actions): bool
    {
        return $actions !== [] && ! collect($actions)
            ->contains(fn (string $action): bool => ! self::roleHasPermission($record, "{$moduleKey}.{$action}"));
    }

    private static function roleHasPermission(?Role $record, string $permission): bool
    {
        return $record?->permissions->contains('name', $permission) ?? false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Role')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('permissions_count')
                    ->label('Jumlah Permission')
                    ->counts('permissions')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->recordActions([
                EditAction::make()->iconButton()->tooltip('Ubah'),
                DeleteAction::make()->iconButton()->tooltip('Hapus'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoles::route('/'),
            'create' => Pages\CreateRole::route('/create'),
            'edit' => Pages\EditRole::route('/{record}/edit'),
        ];
    }
}
