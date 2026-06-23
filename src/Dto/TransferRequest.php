<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $fromAccountId,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public string $toAccountId,

        #[Assert\NotBlank]
        #[Assert\Positive]
        #[Assert\LessThanOrEqual(1_000_000_000)]
        public int $amountCents,

        #[Assert\NotBlank]
        #[Assert\Currency]
        public string $currency,

        #[Assert\NotBlank]
        #[Assert\Length(min: 8, max: 128)]
        #[Assert\Regex(pattern: '/^[A-Za-z0-9._:-]+$/')]
        public string $idempotencyKey,
    ) {
    }

    public static function fromPayload(array $payload, ?string $idempotencyKey): self
    {
        return new self(
            (string) ($payload['from_account_id'] ?? ''),
            (string) ($payload['to_account_id'] ?? ''),
            (int) ($payload['amount_cents'] ?? 0),
            strtoupper((string) ($payload['currency'] ?? '')),
            (string) ($idempotencyKey ?? ''),
        );
    }

    public function requestHash(): string
    {
        return hash('sha256', json_encode([
            'from_account_id' => $this->fromAccountId,
            'to_account_id' => $this->toAccountId,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
        ], JSON_THROW_ON_ERROR));
    }
}
