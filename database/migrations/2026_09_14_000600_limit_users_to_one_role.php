<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'model_has_roles_one_role_per_user';

    public function up(): void
    {
        $table = config('permission.table_names.model_has_roles');
        $rolesTable = config('permission.table_names.roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?: 'role_id';
        $modelKey = config('permission.column_names.model_morph_key') ?: 'model_id';

        DB::table($table)
            ->where('model_type', User::class)
            ->select($modelKey)
            ->groupBy($modelKey)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($modelKey)
            ->each(function (int $userId) use ($table, $rolesTable, $roleKey, $modelKey): void {
                $roleIds = DB::table($table)
                    ->join($rolesTable, "{$rolesTable}.id", '=', "{$table}.{$roleKey}")
                    ->where("{$table}.model_type", User::class)
                    ->where("{$table}.{$modelKey}", $userId)
                    ->orderByRaw("CASE WHEN {$rolesTable}.name = ? THEN 0 ELSE 1 END", ['master'])
                    ->orderBy("{$table}.{$roleKey}")
                    ->pluck("{$table}.{$roleKey}");

                DB::table($table)
                    ->where('model_type', User::class)
                    ->where($modelKey, $userId)
                    ->whereNotIn($roleKey, [$roleIds->first()])
                    ->delete();
            });

        Schema::table($table, function (Blueprint $table) use ($modelKey): void {
            $table->unique(['model_type', $modelKey], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table(config('permission.table_names.model_has_roles'), function (Blueprint $table): void {
            $table->dropUnique(self::INDEX_NAME);
        });
    }
};
