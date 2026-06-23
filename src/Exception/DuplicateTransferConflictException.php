<?php

declare(strict_types=1);

namespace App\Exception;

final class DuplicateTransferConflictException extends ApiException
{
    public function __construct()
    {
        parent::__construct('idempotency_key_conflict', 'This idempotency key was already used with different transfer details.', 409);
    }
}
