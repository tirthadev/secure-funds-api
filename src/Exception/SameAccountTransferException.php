<?php

declare(strict_types=1);

namespace App\Exception;

final class SameAccountTransferException extends ApiException
{
    public function __construct()
    {
        parent::__construct('same_account_transfer', 'Source and destination accounts must be different.', 422);
    }
}
