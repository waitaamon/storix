<?php

declare(strict_types=1);

namespace Storix\Support;

use Illuminate\Support\Facades\Config;

final class FinancialYear
{
    public static function selected(): mixed
    {
        $class = Config::string('storix.financial_year_service_class', 'App\\Services\\FinancialYearService');

        return (new $class)::selectedFinancialYear();
    }
}
