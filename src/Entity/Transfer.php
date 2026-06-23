<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TransferRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransferRepository::class)]
#[ORM\Table(name: 'transfers')]
#[ORM\UniqueConstraint(name: 'uniq_transfers_idempotency_key', columns: ['idempotency_key'])]
#[ORM\Index(columns: ['from_account_id'], name: 'idx_transfers_from_account')]
#[ORM\Index(columns: ['to_account_id'], name: 'idx_transfers_to_account')]
class Transfer
{
    public const STATUS_COMPLETED = 'completed';

    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 128)]
    private string $idempotencyKey;

    #[ORM\Column(length: 64)]
    private string $requestHash;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Account $fromAccount;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Account $toAccount;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $amountCents;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_COMPLETED;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?DateTimeImmutable $completedAt = null;

    public function __construct(
        string $idempotencyKey,
        string $requestHash,
        Account $fromAccount,
        Account $toAccount,
        int $amountCents,
        string $currency,
    ) {
        $this->id = Uuid::v7()->toRfc4122();
        $this->idempotencyKey = $idempotencyKey;
        $this->requestHash = $requestHash;
        $this->fromAccount = $fromAccount;
        $this->toAccount = $toAccount;
        $this->amountCents = (string) $amountCents;
        $this->currency = strtoupper($currency);
        $this->createdAt = new DateTimeImmutable();
        $this->completedAt = $this->createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function getAmountCents(): int
    {
        return (int) $this->amountCents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'idempotency_key' => $this->idempotencyKey,
            'from_account_id' => $this->fromAccount->getId(),
            'to_account_id' => $this->toAccount->getId(),
            'amount_cents' => $this->getAmountCents(),
            'currency' => $this->currency,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'completed_at' => $this->completedAt?->format(DATE_ATOM),
        ];
    }
}
