<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Account;
use App\Exception\AccountNotFoundException;
use App\Repository\AccountRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/accounts')]
final readonly class AccountController
{
    public function __construct(private AccountRepository $accounts)
    {
    }

    #[Route('/{id}', name: 'api_accounts_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $account = $this->accounts->find($id);
        if (!$account instanceof Account) {
            throw new AccountNotFoundException($id);
        }

        return new JsonResponse(['account' => $account->toArray()]);
    }
}
