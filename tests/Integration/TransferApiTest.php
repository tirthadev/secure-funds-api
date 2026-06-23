<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TransferApiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    private const FROM_ACCOUNT = '018f7f2e-4f0d-7b31-a932-aaaaaaaaaaaa';
    private const TO_ACCOUNT = '018f7f2e-4f0d-7b31-a932-bbbbbbbbbbbb';

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);

        $this->entityManager->persist(new Account('USD', 100_000, self::FROM_ACCOUNT));
        $this->entityManager->persist(new Account('USD', 10_000, self::TO_ACCOUNT));
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    public function testCreatesTransferAtomically(): void
    {
        $response = $this->postTransfer('transfer-key-001', 12_345);

        self::assertSame(201, $this->client->getResponse()->getStatusCode());
        self::assertSame('completed', $response['transfer']['status']);
        self::assertSame(12_345, $response['transfer']['amount_cents']);

        self::assertSame(87_655, $this->balance(self::FROM_ACCOUNT));
        self::assertSame(22_345, $this->balance(self::TO_ACCOUNT));
    }

    public function testIdempotentReplayReturnsSameTransferWithoutMovingMoneyAgain(): void
    {
        $first = $this->postTransfer('transfer-key-002', 5_000);
        $second = $this->postTransfer('transfer-key-002', 5_000);

        self::assertSame($first['transfer']['id'], $second['transfer']['id']);
        self::assertSame(95_000, $this->balance(self::FROM_ACCOUNT));
        self::assertSame(15_000, $this->balance(self::TO_ACCOUNT));
    }

    public function testIdempotencyKeyCannotBeReusedForDifferentPayload(): void
    {
        $this->postTransfer('transfer-key-003', 5_000);
        $response = $this->postTransfer('transfer-key-003', 6_000);

        self::assertSame(409, $this->client->getResponse()->getStatusCode());
        self::assertSame('idempotency_key_conflict', $response['error']['code']);
        self::assertSame(95_000, $this->balance(self::FROM_ACCOUNT));
    }

    public function testRejectsInsufficientFunds(): void
    {
        $response = $this->postTransfer('transfer-key-004', 150_000);

        self::assertSame(422, $this->client->getResponse()->getStatusCode());
        self::assertSame('insufficient_funds', $response['error']['code']);
        self::assertSame(100_000, $this->balance(self::FROM_ACCOUNT));
        self::assertSame(10_000, $this->balance(self::TO_ACCOUNT));
    }

    public function testRequiresApiKey(): void
    {
        $this->client->request('GET', '/api/accounts/'.self::FROM_ACCOUNT);

        self::assertSame(401, $this->client->getResponse()->getStatusCode());
    }

    private function postTransfer(string $idempotencyKey, int $amountCents): array
    {
        $this->client->request(
            'POST',
            '/api/transfers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_API_KEY' => 'test-secret',
                'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey,
            ],
            content: json_encode([
                'from_account_id' => self::FROM_ACCOUNT,
                'to_account_id' => self::TO_ACCOUNT,
                'amount_cents' => $amountCents,
                'currency' => 'USD',
            ], JSON_THROW_ON_ERROR),
        );

        return json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function balance(string $accountId): int
    {
        $this->entityManager->clear();
        $account = $this->entityManager->find(Account::class, $accountId);

        return $account->getBalanceCents();
    }
}
