<?php

declare(strict_types=1);

namespace Tests;

use App\Support\Access;
use PHPUnit\Framework\TestCase;

class PermissionMatrixTest extends TestCase
{
    public function test_all_grouped_returns_correct_structure(): void
    {
        $grouped = Access::allGrouped();

        $this->assertIsArray($grouped);
        $this->assertArrayHasKey('dashboard', $grouped);
        $this->assertArrayHasKey('transactions', $grouped);
        $this->assertArrayHasKey('items', $grouped);
        $this->assertArrayHasKey('cashouts', $grouped);
        $this->assertArrayHasKey('settings', $grouped);

        $this->assertArrayHasKey('label', $grouped['dashboard']);
        $this->assertArrayHasKey('key', $grouped['dashboard']);
        $this->assertArrayHasKey('actions', $grouped['dashboard']);

        $this->assertIsArray($grouped['dashboard']['actions']);
        $this->assertArrayHasKey('view', $grouped['dashboard']['actions']);

        $this->assertGreaterThan(30, count(Access::all()));
        $this->assertEquals(9, count($grouped)); // 9 modules
    }

    public function test_module_labels_are_human_readable(): void
    {
        $grouped = Access::allGrouped();

        $this->assertEquals('Dashboard', $grouped['dashboard']['label']);
        $this->assertEquals('Transaksi', $grouped['transactions']['label']);
        $this->assertEquals('Barang', $grouped['items']['label']);
        $this->assertEquals('Daftar Harga', $grouped['price_lists']['label']);
        $this->assertEquals('Cashout', $grouped['cashouts']['label']);
        $this->assertEquals('Pengaturan', $grouped['settings']['label']);
        $this->assertEquals('Role Management', $grouped['roles']['label']);
    }

    public function test_action_labels_are_human_readable(): void
    {
        $grouped = Access::allGrouped();
        $txActions = $grouped['transactions']['actions'];

        $this->assertEquals('Lihat Semua', $txActions['view_any']);
        $this->assertEquals('Lihat', $txActions['view']);
        $this->assertEquals('Tambah', $txActions['create']);
        $this->assertEquals('Ubah', $txActions['update']);
        $this->assertEquals('Hapus', $txActions['delete']);
        $this->assertArrayNotHasKey('delete_any', $txActions);
    }

    public function test_module_order_is_predictable(): void
    {
        $grouped = Access::allGrouped();
        $keys = array_keys($grouped);

        $this->assertEquals('dashboard', $keys[0]);
        $this->assertEquals('transactions', $keys[1]);
        $this->assertEquals('items', $keys[2]);
    }

    public function test_action_order_within_module_is_crud_order(): void
    {
        $grouped = Access::allGrouped();
        $txActions = array_keys($grouped['transactions']['actions']);

        $this->assertEquals('view', $txActions[0]);
        $this->assertEquals('view_any', $txActions[1]);
        $this->assertEquals('create', $txActions[2]);
        $this->assertEquals('update', $txActions[3]);
        $this->assertEquals('view_financial', $txActions[4]);
        $this->assertEquals('unlock', $txActions[5]);
        $this->assertEquals('delete', $txActions[6]);
    }

    public function test_unknown_module_gets_auto_label(): void
    {
        $label = Access::moduleLabel('unknown_module_xyz');
        $this->assertEquals('Unknown Module Xyz', $label);
    }

    public function test_unknown_action_gets_auto_label(): void
    {
        $label = Access::actionLabel('custom_action_xyz');
        $this->assertEquals('Custom Action Xyz', $label);
    }

    public function test_static_all_returns_all_permutations(): void
    {
        $all = Access::all();
        $this->assertGreaterThan(30, count($all));
    }
}
