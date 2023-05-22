<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Enums;

/**
 * General ledger account types.
 */

enum LedgerType: string
{
    //
    case ASSET = 'asset';
    case EXPENSE = 'expense';

    //
    case LIABILITY = 'liability';
    case EQUITY = 'equity'; // aka capital
    case INCOME = 'income'; // aka revenue
}
