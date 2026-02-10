<?php

declare(strict_types=1);

namespace CrmArchetype\Onboarding\Infrastructure\Webhook;

use CrmArchetype\Onboarding\Application\Command\RecordExternalOutcomeCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final readonly class KycWebhookController
{
    public function __construct(
        private MessageBusInterface $commandBus,
    ) {}

    #[Route('/api/webhooks/kyc/{actionId}', name: 'webhook_kyc', methods: ['POST'])]
    public function __invoke(Request $request, string $actionId): Response
    {
        /** @var array{status: string, reason?: string, verificationId?: string} $payload */
        $payload = $request->toArray();

        $outcomeDescription = match ($payload['status'] ?? '') {
            'verified' => 'Zaakceptowane',
            'needs_info' => 'DoUzupelnienia',
            'rejected' => 'Odrzucone',
            default => throw new \InvalidArgumentException(
                sprintf('Unknown KYC status: "%s"', $payload['status'] ?? ''),
            ),
        };

        $this->commandBus->dispatch(new RecordExternalOutcomeCommand(
            actionId: $actionId,
            outcomeDescription: $outcomeDescription,
            outcomeReason: $payload['reason'] ?? null,
            externalReference: $payload['verificationId'] ?? null,
            vendorPayload: $payload,
        ));

        return new JsonResponse(['status' => 'accepted'], Response::HTTP_ACCEPTED);
    }
}
