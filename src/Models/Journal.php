<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Models;

/**
 * A journal is a record of a transactions for a single parent model instance.
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Money\Money;
use Money\Currency;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * @property    Money $balance
 * @property    string $currency
 * @property    CarbonInterface $updated_at
 * @property    CarbonInterface $post_date
 * @property    CarbonInterface $created_at
 * @property    Model $morphed
 * @property    Ledger $ledger
 */
class Journal extends Model
{
    /**
     * @var string
     */
    protected $table = 'accounting_journals';

    /**
     * Relationship to all the model instance this journal applies to.
     *
     * @todo a better name would be good. Maybe journalFor()?
     *
     * @return MorphTo
     */
    public function morphed(): MorphTo
    {
        return $this->morphTo();
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(config('accounting.model-classes.ledger'));
    }

    /**
     * @var array
     */
    protected $casts = [
        // 'deleted_at' => 'timestamp',
    ];

    protected static function boot()
    {
        parent::boot();

        // @todo when created, there will be no transactions and so no balance.
        // Instead, set the balance default to zero though an attribiute.

        static::created(
            fn (Journal $journal) => $journal->resetCurrentBalances()
        );

        // parent::boot();
    }

    // Since currency is mandatory, this could only be used to change the currency,
    // and that does not sound like a sensible thing to do at all.
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function assignToLedger(Ledger $ledger): self
    {
        $ledger->journals()->save($this);

        return $this;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(config('accounting.model-classes.journal-transaction'));
    }

    public function resetCurrentBalances(): Money
    {
        $this->balance = $this->balance();
        $this->save();

        return $this->balance;
    }

    /**
     * @todo replace with a cast.
     *
     * @param Money|int $value
     */
    protected function getBalanceAttribute($value): Money
    {
        return new Money($value, new Currency($this->currency));
    }

    /**
     * @todo replace with a cast.
     * @todo make sure the correct currency has been supplied.
     *
     * @param Money|int $value
     */
    protected function setBalanceAttribute($value): void
    {
        $value = is_a($value, Money::class)
            ? $value
            : new Money($value, new Currency($this->currency));

        $this->attributes['balance'] = $value ? (int)$value->getAmount() : null;
    }

    /**
     * Get the debit only balance of the journal based on a given day (inclusive).
     *
     * @param CarbonInterface $date
     * @return Money
     */
    public function debitBalanceOn(CarbonInterface $date): Money
    {
        $balanceMinorUnits = $this->transactions()
            ->where('post_date', '<=', $date->endOfDay())
            ->where('currency', '=', $this->currency)
            ->sum('debit') ?: 0;

        return new Money($balanceMinorUnits, new Currency($this->currency));

    }

    /**
     * Get the credit only balance of the journal based on a given day (inclusive).
     *
     * @param CarbonInterface $date
     * @return Money
     */
    public function creditBalanceOn(CarbonInterface $date): Money
    {
        $balanceMinorUnits = $this->transactions()
            ->where('post_date', '<=', $date->endOfDay())
            ->where('currency', '=', $this->currency)
            ->sum('credit') ?: 0;

        return new Money($balanceMinorUnits, new Currency($this->currency));
    }

    /**
     * @deprecated replaced with a polymorphic relationship.
     */
    public function transactionsReferencingObjectQuery(Model $object): HasMany
    {
        return $this
            ->transactions()
            ->where('reference_type', get_class($object))
            ->where('reference_id', $object->id);
    }

    /**
     * Get the balance of the journal for a given date.
     *
     * @param CarbonInterface $date
     * @return Money
     */
    public function balanceOn(CarbonInterface $date): Money
    {
        return $this->creditBalanceOn($date)->subtract($this->debitBalanceOn($date));
    }

    /**
     * Get the balance of the journal today, excluding future transactions (after today).
     *
     * @return Money
     */
    public function currentBalance(): Money
    {
        return $this->balanceOn(Carbon::now());
    }

    /**
     * Get the balance of the journal. This *could* include future dates.
     *
     * @return Money
     */
    public function balance(): Money
    {
        if ($this->transactions()->count() > 0) {
            $creditBalance = $this->transactions()
                ->where('currency', '=', $this->currency)
                ->sum('credit');

            $debitBalance = $this->transactions()
                ->where('currency', '=', $this->currency)
                ->sum('debit');

            $balance = $creditBalance - $debitBalance;
        } else {
            $balance = 0;
        }

        return new Money($balance, new Currency($this->currency));
    }

    /**
     * Create a credit journal entry.
     *
     * @param Money|int $value
     * @param string|null $memo
     * @param CarbonInterface|null $post_date
     * @param string|null $transaction_group
     * @return JournalTransaction
     */
    public function credit(
        $value,
        ?string $memo = null,
        ?CarbonInterface $post_date = null,
        ?string $transaction_group = null,
    ): JournalTransaction
    {
        $value = is_a($value, Money::class)
            ? $value->absolute()
            : new Money(abs($value), new Currency($this->currency));

        return $this->post($value, null, $memo, $post_date, $transaction_group);
    }

    /**
     * Debit the journal with a new entry.
     *
     * @param Money|int $value
     * @param string|null $memo
     * @param CarbonInterface|null $post_date
     * @param string|null $transaction_group
     * @return JournalTransaction
     */
    public function debit(
        $value,
        ?string $memo = null,
        ?CarbonInterface $post_date = null,
        ?string $transaction_group = null,
    ): JournalTransaction
    {
        $value = is_a($value, Money::class)
            ? $value->absolute()
            : new Money(abs($value), new Currency($this->currency));

        return $this->post(null, $value, $memo, $post_date, $transaction_group);
    }

    /**
     * Create a journal entry (a debit or a credit).
     *
     * @todo make sure the correct currency has been supplied.
     *
     * @param Money|null $credit
     * @param Money|null $debit
     * @param string|null $memo
     * @param CarbonInterface|null $postDate
     * @param string|null $transactionGroup
     * @return JournalTransaction
     */
    private function post(
        ?Money $credit = null,
        ?Money $debit = null,
        ?string $memo = null,
        ?CarbonInterface $postDate = null,
        ?string $transactionGroup = null,
    ): JournalTransaction {
        $transaction = new JournalTransaction;

        $transaction->credit = $credit ? $credit->getAmount() : null;
        $transaction->debit = $debit ? $debit->getAmount() : null;

        $currencyCode = $credit?->getCurrency()->getCode()
            ?? $debit->getCurrency()->getCode();

        $transaction->memo = $memo;
        $transaction->currency = $currencyCode;
        $transaction->post_date = $postDate ?: Carbon::now();
        $transaction->transaction_group = $transactionGroup;

        $this->transactions()->save($transaction);

        return $transaction;
    }
}
