<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\AccountRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(columns: ['currency'], name: 'idx_accounts_currency')]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    private string $id;

    #[ORM\Column(length: 3)]
    private string $currency;

    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private string $balanceCents;

    #[ORM\Column]
    private DateTimeImmutable $createdAt;

    #[ORM\Column]
    private DateTimeImmutable $updatedAt;

    public function __construct(string $currency, int $balanceCents = 0, ?string $id = null)
    {
        $this->id = $id ?? Uuid::v7()->toRfc4122();
        $this->currency = strtoupper($currency);
        $this->balanceCents = (string) $balanceCents;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getBalanceCents(): int
    {
        return (int) $this->balanceCents;
    }

    public function debit(int $amountCents): void
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Debit amount must be positive.');
        }

        $newBalance = $this->getBalanceCents() - $amountCents;
        if ($newBalance < 0) {
            throw new \DomainException('Insufficient funds.');
        }

        $this->balanceCents = (string) $newBalance;
        $this->touch();
    }

    public function credit(int $amountCents): void
    {
        if ($amountCents <= 0) {
            throw new \InvalidArgumentException('Credit amount must be positive.');
        }

        $this->balanceCents = (string) ($this->getBalanceCents() + $amountCents);
        $this->touch();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'currency' => $this->currency,
            'balance_cents' => $this->getBalanceCents(),
        ];
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
