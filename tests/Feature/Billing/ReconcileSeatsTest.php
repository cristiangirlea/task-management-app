<?php

namespace Tests\Feature\Billing;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesSubscriptions;
use Tests\TestCase;

/**
 * billing:reconcile-seats repairs Stripe seat counts left stale when an
 * update could not reach Stripe.
 */
class ReconcileSeatsTest extends TestCase
{
    use CreatesSubscriptions;

    /** A paying workspace with $members members and $billed seats in Stripe. */
    private function workspace(int $members, int $billed, array $subscription = []): Tenant
    {
        $tenant = Tenant::factory()->create();
        User::factory()->create(['tenant_id' => $tenant->id]);
        User::factory()->member()->count($members - 1)->create(['tenant_id' => $tenant->id]);
        $this->subscribe($tenant, $subscription + ['quantity' => $billed]);

        return $tenant;
    }

    public function test_a_workspace_billed_for_the_wrong_number_of_seats_is_corrected(): void
    {
        $tenant = $this->workspace(members: 4, billed: 3);

        $this->artisan('billing:reconcile-seats')
            ->expectsOutputToContain("Workspace {$tenant->id}: billed for 3 seats, has 4 members: updated.")
            ->expectsOutputToContain('Checked 1 paying workspaces; 1 out of line, 0 still failing.')
            ->assertSuccessful();

        $this->assertSame([['tenant_id' => $tenant->id, 'seats' => 4]], $this->seatSync()->synced);
        $this->assertSame(4, $tenant->subscription('default')->refresh()->quantity);
    }

    public function test_workspaces_already_in_line_are_left_alone(): void
    {
        $this->workspace(members: 2, billed: 2);

        $this->artisan('billing:reconcile-seats')
            ->expectsOutputToContain('Checked 1 paying workspaces; 0 out of line')
            ->assertSuccessful();

        $this->assertSame([], $this->seatSync()->synced);
    }

    public function test_only_paying_workspaces_are_checked(): void
    {
        $this->workspace(members: 3, billed: 1, subscription: ['stripe_status' => 'canceled', 'ends_at' => now()->subDay()]);
        $this->workspace(members: 3, billed: 1, subscription: ['stripe_status' => 'incomplete']);
        User::factory()->count(2)->create();
        $pastDue = $this->workspace(members: 3, billed: 1, subscription: ['stripe_status' => 'past_due']);
        $cancelling = $this->workspace(members: 3, billed: 1, subscription: ['ends_at' => now()->addWeek()]);

        $this->artisan('billing:reconcile-seats')
            ->expectsOutputToContain('Checked 2 paying workspaces; 2 out of line')
            ->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$pastDue->id, $cancelling->id],
            array_column($this->seatSync()->synced, 'tenant_id'),
        );
    }

    public function test_a_dry_run_reports_without_changing_anything(): void
    {
        $tenant = $this->workspace(members: 4, billed: 3);

        $this->artisan('billing:reconcile-seats', ['--dry-run' => true])
            ->expectsOutputToContain("Workspace {$tenant->id}: billed for 3 seats, has 4 members")
            ->expectsOutputToContain('(dry run, nothing changed)')
            ->assertSuccessful();

        $this->assertSame([], $this->seatSync()->synced);
        $this->assertSame(3, $tenant->subscription('default')->refresh()->quantity);
    }

    public function test_an_update_stripe_refuses_is_reported_and_fails_the_run(): void
    {
        $this->seatSync()->failing = true;
        $tenant = $this->workspace(members: 4, billed: 3);

        $this->artisan('billing:reconcile-seats')
            ->expectsOutputToContain("Workspace {$tenant->id}: billed for 3 seats, has 4 members: Stripe did not accept the update")
            ->expectsOutputToContain('1 still failing')
            ->assertFailed();
    }

    public function test_it_runs_every_hour(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => Str::contains($event->command, 'billing:reconcile-seats'));

        $this->assertNotNull($event, 'billing:reconcile-seats is not scheduled');
        $this->assertSame('0 * * * *', $event->expression);
    }
}
