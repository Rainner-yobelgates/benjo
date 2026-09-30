<?php

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCommissionParticipantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_selected_active_participant_receives_their_own_percentage_of_gross_income(): void
    {
        $transaction = $this->makeTransaction(100_000);
        $firstUser = $this->makeCommissionUser(15);
        $secondUser = $this->makeCommissionUser(15);

        $transaction->syncCommissionParticipants([$firstUser->id, $secondUser->id]);

        $this->assertSame([$firstUser->id, $secondUser->id], $transaction->participants()
            ->orderBy('users.id')
            ->pluck('users.id')
            ->all());
        $this->assertSame([15_000.0, 15_000.0], $transaction->commissions()
            ->orderBy('user_id')
            ->pluck('amount')
            ->map(fn (string $amount): float => (float) $amount)
            ->all());
    }

    public function test_transaction_without_participants_does_not_create_commissions(): void
    {
        $transaction = $this->makeTransaction(100_000);

        $transaction->syncCommissionParticipants([]);

        $this->assertCount(0, $transaction->commissions()->get());
    }

    public function test_selected_users_with_a_positive_percentage_receive_commission(): void
    {
        $transaction = $this->makeTransaction(100_000);
        $commissionUser = $this->makeCommissionUser(15);
        $zeroPercentUser = $this->makeCommissionUser(0);

        $transaction->syncCommissionParticipants([$commissionUser->id, $zeroPercentUser->id]);

        $this->assertSame([$commissionUser->id], $transaction->participants()->pluck('users.id')->all());
        $this->assertSame([15_000.0], $transaction->commissions()
            ->pluck('amount')
            ->map(fn (string $amount): float => (float) $amount)
            ->all());
    }

    public function test_draft_commission_follows_the_latest_total_and_user_configuration(): void
    {
        $transaction = $this->makeTransaction(100_000);
        $user = $this->makeCommissionUser(15);
        $transaction->syncCommissionParticipants([$user->id]);

        $user->update(['commission_percent' => 30]);
        $transaction->update(['service_fee' => 200_000]);

        $commission = $transaction->commissions()->firstOrFail();

        $this->assertSame(30.0, (float) $commission->percent);
        $this->assertSame(60_000.0, (float) $commission->amount);
    }

    public function test_locked_commission_remains_a_snapshot_until_the_transaction_is_unlocked(): void
    {
        $transaction = $this->makeTransaction(100_000);
        $user = $this->makeCommissionUser(15);
        $transaction->syncCommissionParticipants([$user->id]);
        $transaction->lock();

        $user->update(['commission_percent' => 30]);
        $transaction->update(['service_fee' => 200_000]);

        $commission = $transaction->commissions()->firstOrFail();

        $this->assertTrue($transaction->fresh()->isLocked());
        $this->assertSame(15.0, (float) $commission->percent);
        $this->assertSame(15_000.0, (float) $commission->amount);

        $transaction->unlock();

        $commission->refresh();

        $this->assertTrue($transaction->fresh()->isDraft());
        $this->assertSame(30.0, (float) $commission->percent);
        $this->assertSame(60_000.0, (float) $commission->amount);
    }

    private function makeTransaction(float $serviceFee): Transaction
    {
        return Transaction::query()->create([
            'customer_name' => 'Customer Test',
            'service_fee' => $serviceFee,
        ]);
    }

    private function makeCommissionUser(float $percent): User
    {
        return User::factory()->create([
            'commission_percent' => $percent,
        ]);
    }
}
