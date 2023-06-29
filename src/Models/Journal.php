<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Models;

/**
 * A journal is a record of a transactions for a single parent model instance.
 */

use Money\Money;
use Carbon\Carbon;
use Money\Currency;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Scottlaurent\Accounting\Casts\MoneyCast;
use Scottlaurent\Accounting\Casts\CurrencyCast;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property Money $balance
 * @property string $currency_code ISO 4217
 * @property Currency $currency
 * @property CarbonInterface $updated_at
 * @property CarbonInterface $post_date
 * @property CarbonInterface $created_at
 * @property Model $morphed deprecated; use owner
 * @property Model $owner
 * @property Ledger|null $ledger
 */
class Journal extends Model
{
    /**
     * @var string
     */
    protected $table = 'accounting_journals';

    /**
     * @var array
     */
    protected $casts = [
        'currency' => CurrencyCast::class . ':currency_code',
        'balance' => MoneyCast::class . ':currency_code,balance',
    ];

    /**
     * Relationship to all the model instance this journal applies to.
     *
     * @todo use owner
     *
     * @return MorphTo
     */
    public function morphed(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The model instance this journal applies to.
     *
     * @return MorphTo
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner', 'morphed_type', 'morphed_id');
    }

    public function ledger(): BelongsTo
    {
        return $this->belongsTo(config('accounting.model-classes.ledger'));
    }

    protected static function boot()
    {
        parent::boot();

        // @todo when created, there will be no transactions and so no balance.
        // Instead, set the balance default to zero though an attribute.

        static::created(
            fn (Journal $journal) => $journal->resetCurrentBalance()
        );
    }

    /**
     * @todo make sure the currencies match.
     */
    public function assignToLedger(Ledger $ledger): self
    {
        $ledger->journals()->save($this);

        return $this;
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(config('accounting.model-classes.journal-transaction'));
    }

    public function resetCurrentBalance(): Money
    {
        $this->balance = $this->totalBalance();
        $this->save();

        // Log::debug('Updating ledger balance', ['journalId' => $this->id, 'balance' => $this->balance]);

        return $this->balance;
    }

    /**
     * Get the debit only balance of the journal at the end of a given day.
     *
     * @param CarbonInterface $date
     * @return Money
     */
    public function debitBalanceOn(CarbonInterface $date): Money
    {
        $balanceMinorUnits = $this->transactions()
            ->where('post_date', '<=', $date->endOfDay())
            ->where('currency_code', '=', $this->currency_code)
            ->sum('debit') ?: 0;

        return new Money($balanceMinorUnits, $this->currency);
    }

    /**
     * Get the credit only balance of the journal at the end of a given day.
     *
     * @param CarbonInterface $date
     * @return Money
     */
    public function creditBalanceOn(CarbonInterface $date): Money
    {
        $balanceMinorUnits = $this->transactions()
            ->where('post_date', '<=', $date->endOfDay())
            ->where('currency_code', '=', $this->currency_code)
            ->sum('credit') ?: 0;

        return new Money($balanceMinorUnits, $this->currency);
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
     * Get the balance of the journal taking all transactions into account.
     * This *could* include future dates.
     *
     * @return Money
     */
    public function totalBalance(): Money
    {
        $creditBalanceMinorUnits = (int)$this->transactions()
            ->where('currency_code', '=', $this->currency_code)
            ->sum('credit');

        $debitBalanceMinorUnits = (int)$this->transactions()
            ->where('currency_code', '=', $this->currency_code)
            ->sum('debit');

        $balance = $creditBalanceMinorUnits - $debitBalanceMinorUnits;

        return new Money($balance, $this->currency);
    }

    /**
     * Remove matching journal entries.
     *
     * We want to remove transactions that match:
     *
     * - The given reference.
     * - Any other arbitrary conditions (a query callback can do this).
     *
     * Some thought on how transaction groups would be handled is needed.
     * Maybe for now only allow removal of entries that are no in a ledger.
     *
     * @return void
     */
    public function remove()
    {
        //
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
            : new Money(abs($value), $this->currency);

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
            : new Money(abs($value), $this->currency);

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

        $transaction->credit = $credit;
        $transaction->debit = $debit;

        // @todo use the journal currency, after confirming the correct
        // currency has been passed in.

        $currency = $credit?->getCurrency() ?? $debit->getCurrency();

        $transaction->memo = $memo;
        // @todo the transaction needs to cast currency to an object,
        // so this will change to: `$transaction->currency = $this->currency`
        $transaction->currency = $currency;
        $transaction->post_date = $postDate ?: Carbon::now();
        $transaction->transaction_group = $transactionGroup;

        $this->transactions()->save($transaction);

        return $transaction;
    }
}
