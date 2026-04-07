<?php

namespace Tests\Unit;

use App\Modules\Lifecycle\Services\AccessResolver;
use App\Modules\Subscriptions\Models\Subscription;
use App\Shared\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AccessResolverTest extends TestCase
{
    public function test_it_grants_access_for_valid_trial_active_and_grace_states(): void
    {
        $resolver = new AccessResolver();
        $checkedAt = CarbonImmutable::parse('2026-03-01 10:00:00');

        $trialing = new Subscription([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => '2026-03-05 10:00:00',
        ]);

        $active = new Subscription([
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => '2026-03-10 10:00:00',
        ]);

        $pastDue = new Subscription([
            'status' => SubscriptionStatus::PastDue,
            'grace_period_ends_at' => '2026-03-02 10:00:00',
        ]);

        $this->assertTrue($resolver->isGranted($trialing, $checkedAt));
        $this->assertTrue($resolver->isGranted($active, $checkedAt));
        $this->assertTrue($resolver->isGranted($pastDue, $checkedAt));
    }

    public function test_it_denies_access_for_expired_or_canceled_states(): void
    {
        $resolver = new AccessResolver();
        $checkedAt = CarbonImmutable::parse('2026-03-10 10:00:00');

        $expiredTrial = new Subscription([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => '2026-03-01 10:00:00',
        ]);

        $expiredActive = new Subscription([
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => '2026-03-01 10:00:00',
        ]);

        $expiredGrace = new Subscription([
            'status' => SubscriptionStatus::PastDue,
            'grace_period_ends_at' => '2026-03-01 10:00:00',
        ]);

        $canceled = new Subscription([
            'status' => SubscriptionStatus::Canceled,
        ]);

        $this->assertFalse($resolver->isGranted($expiredTrial, $checkedAt));
        $this->assertFalse($resolver->isGranted($expiredActive, $checkedAt));
        $this->assertFalse($resolver->isGranted($expiredGrace, $checkedAt));
        $this->assertFalse($resolver->isGranted($canceled, $checkedAt));
    }

    public function test_it_revokes_access_at_exact_trial_and_active_boundaries(): void
    {
        $resolver = new AccessResolver();

        $trialing = new Subscription([
            'status' => SubscriptionStatus::Trialing,
            'trial_ends_at' => '2026-03-05 10:00:00',
        ]);

        $active = new Subscription([
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => '2026-03-05 10:00:00',
        ]);

        $beforeBoundary = CarbonImmutable::parse('2026-03-05 09:59:59');
        $atBoundary = CarbonImmutable::parse('2026-03-05 10:00:00');

        $this->assertTrue($resolver->isGranted($trialing, $beforeBoundary));
        $this->assertFalse($resolver->isGranted($trialing, $atBoundary));
        $this->assertTrue($resolver->isGranted($active, $beforeBoundary));
        $this->assertFalse($resolver->isGranted($active, $atBoundary));
    }
}
