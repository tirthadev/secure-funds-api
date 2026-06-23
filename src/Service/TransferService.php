<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TransferRequest;
use App\Entity\Account;
use App\Entity\Transfer;
use App\Exception\AccountNotFoundException;
use App\Exception\CurrencyMismatchException;
use App\Exception\DuplicateTransferConflictException;
use App\Exception\InsufficientFundsException;
use App\Exception\SameAccountTransferException;
use App\Repository\AccountRepository;
use App\Repository\TransferRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class TransferService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccountRepository $accounts,
        private TransferRepository $transfers,
        private IdempotencyCache $idempotencyCache,
        private LoggerInterface $logger,
    ) {
    }

    public function transfer(TransferRequest $request): array
    {
        if ($request->fromAccountId === $request->toAccountId) {
            throw new SameAccountTransferException();
        }

        $cached = $this->idempotencyCache->get($request->idempotencyKey);
        if ($cached !== null && ($cached['request_hash'] ?? null) === $request->requestHash()) {
            return $cached['transfer'];
        }

        $existing = $this->transfers->findOneBy(['idempotencyKey' => $request->idempotencyKey]);
        if ($existing instanceof Transfer) {
            if ($existing->getRequestHash() !== $request->requestHash()) {
                throw new DuplicateTransferConflictException();
            }

            $response = $existing->toArray();
            $this->idempotencyCache->put($request->idempotencyKey, [
                'request_hash' => $request->requestHash(),
                'transfer' => $response,
            ]);

            return $response;
        }

        try {
            return $this->entityManager->wrapInTransaction(function () use ($request): array {
                [$fromAccount, $toAccount] = $this->lockAccounts($request->fromAccountId, $request->toAccountId);

                if ($fromAccount->getCurrency() !== $request->currency || $toAccount->getCurrency() !== $request->currency) {
                    throw new CurrencyMismatchException();
                }

                try {
                    $fromAccount->debit($request->amountCents);
                } catch (\DomainException) {
                    throw new InsufficientFundsException();
                }
                $toAccount->credit($request->amountCents);

                $transfer = new Transfer(
                    $request->idempotencyKey,
                    $request->requestHash(),
                    $fromAccount,
                    $toAccount,
                    $request->amountCents,
                    $request->currency,
                );

                $this->entityManager->persist($transfer);
                $this->entityManager->flush();

                $response = $transfer->toArray();
                $this->idempotencyCache->put($request->idempotencyKey, [
                    'request_hash' => $request->requestHash(),
                    'transfer' => $response,
                ]);

                $this->logger->info('Transfer completed.', [
                    'transfer_id' => $transfer->getId(),
                    'from_account_id' => $request->fromAccountId,
                    'to_account_id' => $request->toAccountId,
                    'amount_cents' => $request->amountCents,
                    'currency' => $request->currency,
                ]);

                return $response;
            });
        } catch (UniqueConstraintViolationException) {
            $this->entityManager->clear();
            $transfer = $this->transfers->findOneBy(['idempotencyKey' => $request->idempotencyKey]);
            if ($transfer instanceof Transfer && $transfer->getRequestHash() === $request->requestHash()) {
                return $transfer->toArray();
            }

            throw new DuplicateTransferConflictException();
        } catch (Throwable $exception) {
            if ($exception instanceof \RuntimeException) {
                throw $exception;
            }

            $this->logger->error('Transfer failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /** @return array{0: Account, 1: Account} */
    private function lockAccounts(string $fromAccountId, string $toAccountId): array
    {
        $idsInLockOrder = [$fromAccountId, $toAccountId];
        sort($idsInLockOrder, SORT_STRING);

        $locked = [];
        foreach ($idsInLockOrder as $accountId) {
            $account = $this->accounts->find($accountId);
            if (!$account instanceof Account) {
                throw new AccountNotFoundException($accountId);
            }

            $this->entityManager->lock($account, LockMode::PESSIMISTIC_WRITE);
            $locked[$accountId] = $account;
        }

        return [$locked[$fromAccountId], $locked[$toAccountId]];
    }
}
