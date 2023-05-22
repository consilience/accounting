<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Models;

use Money\Money;
use Carbon\Carbon;
use Money\Currency;
use Illuminate\Database\Eloquent\Model;
use Scottlaurent\Accounting\Enums\LedgerType;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property    Money $balance
 * @property    Carbon $updated_at
 * @property    Carbon $post_date
 * @property    Carbon $created_at
 */
class Ledger extends Model
{
    /**
     * @var string
     */
    protected $table = 'accounting_ledgers';

    protected $casts = [
        'type' => LedgerType::class,
    ];

    public function journals(): HasMany
    {
        return $this->hasMany(config('accounting.model-classes.journal'));
    }

    /**
     * Get all of the posts for the country.
     */
    public function journal_transactions(): HasManyThrough
    {
        return $this->hasManyThrough(config('accounting.model-classes.journal-transaction'), Journal::class);
    }

    /**
     * Sum up all balances for all journals in this ledger.
     *
     * This relies on all balances being saved to the journals.
     *
     * @todo protect the sum from accidentally mixing currencies.
     * @todo this is possibly *total* balance, rather than *current* balance.
     * The journals hold the total balance that includes future transactions.
     * @todo are the ledger account types even grouped properly here?
     * @todo accept currency object rather than a code.
     *
     * @param string $currency
     * @return Money
     */
    public function currentBalance(string $currency): Money
    {
        $currency = new Currency($currency);

        $debit = $this->journal_transactions->reduce(
            fn ($carry, JournalTransaction $transaction) => $transaction->debit ? $carry->add($transaction->debit) : $carry,
            new Money(0, $currency),
        );

        $credit = $this->journal_transactions->reduce(
            fn ($carry, JournalTransaction $transaction) => $transaction->credit ? $carry->add($transaction->credit) : $carry,
            new Money(0, $currency),
        );

        if ($this->type === LedgerType::ASSET || $this->type === LedgerType::EXPENSE) {
            $balance = $debit->subtract($credit);
        } else {
            $balance = $credit->subtract($debit);
        }

        return $balance;
    }
}
