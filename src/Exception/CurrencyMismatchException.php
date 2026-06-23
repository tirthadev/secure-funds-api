<?php

declare(strict_types=1);

namespace App\Exception;

final class CurrencyMismatchException extends ApiException
{
    public function __construct()
    {
        parent::__construct('currency_mismatch', 'Both accounts and the transfer must use the same currency.', 422);
    }
}
