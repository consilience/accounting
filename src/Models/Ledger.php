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
     *
     * @param string $currency
     * @return Money
     */
    public function getCurrentBalance(string $currency): Money
    {
        if ($this->type === LedgerType::ASSET || $this->type === LedgerType::EXPENSE) {
            $balance = $this->journal_transactions->sum('debit') - $this->journal_transactions->sum('credit');
        } else {
            $balance = $this->journal_transactions->sum('credit') - $this->journal_transactions->sum('debit');
        }

        return new Money($balance, new Currency($currency));
    }
}
