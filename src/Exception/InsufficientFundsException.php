<?php

declare(strict_types=1);

namespace App\Exception;

final class InsufficientFundsException extends ApiException
{
    public function __construct()
    {
        parent::__construct('insufficient_funds', 'The source account does not have enough available balance.', 422);
    }
}
