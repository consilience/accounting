<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Enums;

enum LedgerType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case INCOME = 'income'; // aka REVENUE
    case EXPENSE = 'expense';
}
