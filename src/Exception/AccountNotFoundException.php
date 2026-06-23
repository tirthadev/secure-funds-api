<?php

declare(strict_types=1);

namespace App\Exception;

final class AccountNotFoundException extends ApiException
{
    public function __construct(string $accountId)
    {
        parent::__construct('account_not_found', sprintf('Account "%s" was not found.', $accountId), 404);
    }
}
