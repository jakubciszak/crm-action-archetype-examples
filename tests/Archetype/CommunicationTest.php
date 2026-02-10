<?php

declare(strict_types=1);

namespace CrmArchetype\Tests\Archetype;

use CrmArchetype\Archetype\Action;
use CrmArchetype\Archetype\Communication;
use CrmArchetype\Archetype\CommunicationThread;
use CrmArchetype\Archetype\CustomerServiceCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommunicationTest extends TestCase
{
    #[Test]
    public function communication_generates_actions(): void
    {
        $comm = new Communication('KYC request', new \DateTimeImmutable());

        self::assertEmpty($comm->actions());

        $action = new Action('a1', 'Verify documents');
        $comm->generateAction($action);

        self::assertCount(1, $comm->actions());
        self::assertSame('Verify documents', $comm->actions()[0]->description());
    }

    #[Test]
    public function communication_tracks_dates(): void
    {
        $sent = new \DateTimeImmutable('2025-01-01');
        $comm = new Communication('Test', $sent);

        self::assertSame($sent, $comm->dateSent());
        self::assertNull($comm->dateReceived());

        $comm->markReceived();
        self::assertNotNull($comm->dateReceived());
    }

    #[Test]
    public function thread_groups_communications(): void
    {
        $thread = new CommunicationThread('KYC Phase', 'KYC verification phase');

        self::assertSame('KYC Phase', $thread->topicName());
        self::assertFalse($thread->isClosed());
        self::assertEmpty($thread->communications());

        $comm1 = new Communication('First', new \DateTimeImmutable());
        $comm2 = new Communication('Second', new \DateTimeImmutable());
        $thread->addCommunication($comm1);
        $thread->addCommunication($comm2);

        self::assertCount(2, $thread->communications());

        $thread->close();
        self::assertTrue($thread->isClosed());
    }

    #[Test]
    public function case_contains_threads(): void
    {
        $case = new CustomerServiceCase(
            title: 'Onboarding Acme Corp',
            briefDescription: 'Full B2B onboarding',
            raisedBy: 'sales-rep-1',
            priority: 'high',
        );

        self::assertTrue($case->isOpen());
        self::assertSame('high', $case->priority());
        self::assertEmpty($case->threads());

        $thread = new CommunicationThread('Phase 1');
        $case->addThread($thread);

        self::assertCount(1, $case->threads());

        $case->close();
        self::assertFalse($case->isOpen());
    }
}
