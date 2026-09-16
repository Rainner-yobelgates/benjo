<?php

namespace Tests\Feature;

use App\Filament\Pages\MyCommissionPage;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommissionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_daily_history_includes_only_locked_commissions_and_zero_value_rows(): void
    {
        Carbon::setTestNow('2026-09-14 10:00:00');
        $commissionUser = $this->makeCommissionUser('Andi', 15);
        $userWithoutCommission = $this->makeCommissionUser('Budi', 10);

        $this->makeTransaction('2026-09-14', 100_000, $commissionUser, lock: true);
        $this->makeTransaction('2026-09-13', 100_000, $commissionUser, lock: false);

        $page = app(MyCommissionPage::class);
        $page->period = 'day';
        $rows = $page->commissionHistoryRows;

        $this->assertCount(14, $rows);
        $this->assertSame(15_000.0, $this->amountFor($rows, '2026-09-14', $commissionUser->id));
        $this->assertSame(0.0, $this->amountFor($rows, '2026-09-13', $commissionUser->id));
        $this->assertSame(0.0, $this->amountFor($rows, '2026-09-14', $userWithoutCommission->id));
    }

    public function test_weekly_and_monthly_history_have_the_expected_number_of_buckets(): void
    {
        Carbon::setTestNow('2026-09-14 10:00:00');
        $user = $this->makeCommissionUser('Andi', 15);
        $page = app(MyCommissionPage::class);

        $page->period = 'week';
        $this->assertCount(8, $page->commissionHistoryRows->pluck('key')->unique());

        $page->period = 'month';
        $this->assertCount(12, $page->commissionHistoryRows->pluck('key')->unique());
    }

    public function test_history_lists_each_distinct_commission_snapshot_percentage_in_the_same_period(): void
    {
        Carbon::setTestNow('2026-09-14 10:00:00');
        $user = $this->makeCommissionUser('Andi', 10);
        $this->makeTransaction('2026-09-14', 100_000, $user, lock: true);

        $user->update(['commission_percent' => 15]);
        $this->makeTransaction('2026-09-14', 100_000, $user, lock: true);

        $page = app(MyCommissionPage::class);
        $page->period = 'day';
        $todayRow = $page->commissionHistoryRows
            ->first(fn (array $row): bool => $row['key'] === '2026-09-14' && $row['user_id'] === $user->id);

        $this->assertSame('10%, 15%', $todayRow['percent_label']);
        $this->assertSame(25_000.0, $todayRow['amount']);
        $this->assertSame(2, $todayRow['transaction_count']);
    }

    private function makeCommissionUser(string $name, float $percent): User
    {
        return User::factory()->create([
            'name' => $name,
            'commission_percent' => $percent,
            'commission_active' => true,
        ]);
    }

    private function makeTransaction(string $date, float $serviceFee, User $user, bool $lock): Transaction
    {
        $transaction = new Transaction([
            'customer_name' => 'Customer Test',
            'service_fee' => $serviceFee,
        ]);
        $transaction->transaction_date = $date;
        $transaction->save();
        $transaction->syncCommissionParticipants([$user->id]);

        if ($lock) {
            $transaction->lock();
        }

        return $transaction;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{key: string, user_id: int, amount: float}>  $rows
     */
    private function amountFor($rows, string $key, int $userId): float
    {
        return (float) $rows
            ->first(fn (array $row): bool => $row['key'] === $key && $row['user_id'] === $userId)['amount'];
    }
}
