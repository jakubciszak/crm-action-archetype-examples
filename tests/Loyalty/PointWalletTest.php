<?php

declare(strict_types=1);

namespace Tests\Loyalty;

use Loyalty\Domain\JournalEntry;
use Loyalty\Domain\PointsActivated;
use Loyalty\Domain\PointsDebitedForReturn;
use Loyalty\Domain\PointsExpired;
use Loyalty\Domain\PointsPending;
use Loyalty\Domain\PointWallet;
use Loyalty\Domain\WalletEntryStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PointWalletTest extends TestCase
{
    #[Test]
    public function starts_with_zero_balances(): void
    {
        $wallet = new PointWallet('USR-001');

        self::assertSame(0, $wallet->pendingBalance());
        self::assertSame(0, $wallet->activeBalance());
        self::assertSame([], $wallet->entries());
    }

    #[Test]
    public function credits_pending_from_order_per_line(): void
    {
        $wallet = new PointWallet('USR-001');

        $wallet->creditPending('ORD-001', [
            new JournalEntry(300, 'Laptop', 'ORD-001', 'L1', 'Laptop Dell'),
            new JournalEntry(15, 'Mysz', 'ORD-001', 'L2', 'Mysz Logitech'),
            new JournalEntry(35, 'Klawiatura', 'ORD-001', 'L3', 'Keychron'),
        ]);

        self::assertSame(350, $wallet->pendingBalance());
        self::assertSame(0, $wallet->activeBalance());
        self::assertCount(3, $wallet->entries());
        self::assertCount(3, $wallet->entriesByStatus(WalletEntryStatus::Pending));

        $events = $wallet->releaseEvents();
        self::assertCount(3, $events);
        self::assertInstanceOf(PointsPending::class, $events[0]);
        self::assertSame(300, $events[0]->points);
        self::assertSame('L1', $events[0]->sourceItemRef);
    }

    #[Test]
    public function credits_active_immediately_for_referral(): void
    {
        $wallet = new PointWallet('USR-001');
        $expiresAt = new \DateTimeImmutable('+3 months');

        $wallet->creditActive(
            new JournalEntry(50, 'Referral bonus'),
            $expiresAt,
        );

        self::assertSame(0, $wallet->pendingBalance());
        self::assertSame(50, $wallet->activeBalance());

        $events = $wallet->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsActivated::class, $events[0]);
        self::assertSame(50, $events[0]->points);
    }

    #[Test]
    public function debits_pending_for_product_return(): void
    {
        $wallet = new PointWallet('USR-001');
        $wallet->creditPending('ORD-001', [
            new JournalEntry(300, 'Laptop', 'ORD-001', 'L1', 'Laptop Dell'),
            new JournalEntry(15, 'Mysz', 'ORD-001', 'L2', 'Mysz Logitech'),
        ]);
        $wallet->releaseEvents();

        $debited = $wallet->debitItem('ORD-001', 'L2');

        self::assertSame(15, $debited);
        self::assertSame(300, $wallet->pendingBalance());
        self::assertCount(1, $wallet->entriesByStatus(WalletEntryStatus::Debited));

        $events = $wallet->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsDebitedForReturn::class, $events[0]);
        self::assertSame(15, $events[0]->points);
        self::assertSame('Mysz Logitech', $events[0]->label);
    }

    #[Test]
    public function debit_for_return_throws_on_unknown_line(): void
    {
        $wallet = new PointWallet('USR-001');
        $wallet->creditPending('ORD-001', [
            new JournalEntry(100, 'Laptop', 'ORD-001', 'L1', 'Laptop'),
        ]);

        $this->expectException(\DomainException::class);
        $wallet->debitItem('ORD-001', 'L99');
    }

    #[Test]
    public function activates_remaining_pending_after_return_period(): void
    {
        $wallet = new PointWallet('USR-001');
        $wallet->creditPending('ORD-001', [
            new JournalEntry(300, 'Laptop', 'ORD-001', 'L1', 'Laptop Dell'),
            new JournalEntry(15, 'Mysz', 'ORD-001', 'L2', 'Mysz Logitech'),
            new JournalEntry(35, 'Klawiatura', 'ORD-001', 'L3', 'Keychron'),
        ]);

        // Return one item
        $wallet->debitItem('ORD-001', 'L2');
        $wallet->releaseEvents();

        // Return period expires → remaining pending → active
        $expiresAt = new \DateTimeImmutable('+6 months');
        $activated = $wallet->activateSource('ORD-001', $expiresAt);

        self::assertSame(335, $activated); // 300 + 35, without the returned 15
        self::assertSame(0, $wallet->pendingBalance());
        self::assertSame(335, $wallet->activeBalance());

        $activeEntries = $wallet->entriesByStatus(WalletEntryStatus::Active);
        self::assertCount(2, $activeEntries);
        foreach ($activeEntries as $entry) {
            self::assertSame($expiresAt, $entry->expiresAt());
        }

        $events = $wallet->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsActivated::class, $events[0]);
        self::assertSame(335, $events[0]->points);
    }

    #[Test]
    public function expires_active_entries_past_expiry_date(): void
    {
        $wallet = new PointWallet('USR-001');
        $now = new \DateTimeImmutable('2025-05-12');

        // Referral: active, expires in 3 months
        $wallet->creditActive(
            new JournalEntry(50, 'Referral'),
            $now->modify('+3 months'),
        );

        // Order: active, expires in 6 months
        $wallet->creditPending('ORD-001', [
            new JournalEntry(300, 'Laptop', 'ORD-001', 'L1', 'Laptop'),
        ]);
        $wallet->activateSource('ORD-001', $now->modify('+6 months'));
        $wallet->releaseEvents();

        // 4 months later: referral expired, order still active
        $future = $now->modify('+4 months');
        $expired = $wallet->expireEntries($future);

        self::assertSame(50, $expired);
        self::assertSame(300, $wallet->activeBalance());
        self::assertCount(1, $wallet->entriesByStatus(WalletEntryStatus::Expired));

        $events = $wallet->releaseEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(PointsExpired::class, $events[0]);
        self::assertSame(50, $events[0]->points);
    }

    #[Test]
    public function full_scenario_order_return_activate_expire(): void
    {
        $wallet = new PointWallet('USR-001');
        $now = new \DateTimeImmutable('2025-05-12');

        // Order with 3 lines → pending
        $wallet->creditPending('ORD-001', [
            new JournalEntry(300, 'Laptop', 'ORD-001', 'L1', 'Laptop Dell XPS'),
            new JournalEntry(15, 'Mysz', 'ORD-001', 'L2', 'Mysz Logitech MX'),
            new JournalEntry(35, 'Klawiatura', 'ORD-001', 'L3', 'Klawiatura Keychron'),
        ]);
        self::assertSame(350, $wallet->pendingBalance());

        // Referral → active (3 months)
        $wallet->creditActive(
            new JournalEntry(50, 'Referral bonus'),
            $now->modify('+3 months'),
        );
        self::assertSame(50, $wallet->activeBalance());

        // Return mouse → debit from pending
        $wallet->debitItem('ORD-001', 'L2');
        self::assertSame(335, $wallet->pendingBalance());

        // Return period ends → 335 pending → active (6 months)
        $orderExpiresAt = $now->modify('+14 days')->modify('+6 months');
        $wallet->activateSource('ORD-001', $orderExpiresAt);
        self::assertSame(0, $wallet->pendingBalance());
        self::assertSame(385, $wallet->activeBalance()); // 335 order + 50 referral

        // 4 months: referral expires
        $wallet->expireEntries($now->modify('+4 months'));
        self::assertSame(335, $wallet->activeBalance()); // only order left

        // 7 months: order also expires
        $wallet->expireEntries($now->modify('+7 months'));
        self::assertSame(0, $wallet->activeBalance());

        // Final state
        self::assertCount(1, $wallet->entriesByStatus(WalletEntryStatus::Debited));
        self::assertCount(3, $wallet->entriesByStatus(WalletEntryStatus::Expired));
    }

    #[Test]
    public function entries_by_source_returns_all_for_source(): void
    {
        $wallet = new PointWallet('USR-001');
        $wallet->creditPending('ORD-001', [
            new JournalEntry(100, 'Line 1', 'ORD-001', 'L1', 'Product A'),
            new JournalEntry(200, 'Line 2', 'ORD-001', 'L2', 'Product B'),
        ]);
        $wallet->creditPending('ORD-002', [
            new JournalEntry(50, 'Line 1', 'ORD-002', 'L1', 'Product C'),
        ]);

        $ord1 = $wallet->entriesBySource('ORD-001');
        self::assertCount(2, $ord1);

        $ord2 = $wallet->entriesBySource('ORD-002');
        self::assertCount(1, $ord2);
    }

    #[Test]
    public function cannot_debit_already_debited_entry(): void
    {
        $wallet = new PointWallet('USR-001');
        $wallet->creditPending('ORD-001', [
            new JournalEntry(100, 'Line 1', 'ORD-001', 'L1', 'Product A'),
        ]);

        $wallet->debitItem('ORD-001', 'L1');

        $this->expectException(\DomainException::class);
        $wallet->debitItem('ORD-001', 'L1');
    }
}
