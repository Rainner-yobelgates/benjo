<?php

namespace Tests\Unit;

use App\Models\Transaction;
use App\Models\User;
use App\Policies\TransactionPolicy;
use App\Support\Access;
use Mockery;
use PHPUnit\Framework\TestCase;

class TransactionFinancialPermissionTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_financial_view_requires_its_dedicated_permission(): void
    {
        $transaction = new Transaction;
        $userWithoutPermission = Mockery::mock(User::class);
        $userWithoutPermission
            ->shouldReceive('can')
            ->once()
            ->with(Access::TRANSACTIONS_VIEW_FINANCIAL)
            ->andReturnFalse();
        $userWithPermission = Mockery::mock(User::class);
        $userWithPermission
            ->shouldReceive('can')
            ->once()
            ->with(Access::TRANSACTIONS_VIEW_FINANCIAL)
            ->andReturnTrue();
        $policy = new TransactionPolicy;

        $this->assertFalse($policy->viewFinancial($userWithoutPermission, $transaction));
        $this->assertTrue($policy->viewFinancial($userWithPermission, $transaction));
    }

    public function test_financial_permission_is_grouped_under_transactions(): void
    {
        $actions = Access::allGrouped()['transactions']['actions'];

        $this->assertSame('Ringkasan Keuangan', $actions['view_financial']);
    }
}
