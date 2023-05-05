<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Models;

use Money\Money;
use Money\Currency;
use Illuminate\Support\Str;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property string $id
 * @property string $journal_id
 * @property int $debit
 * @property int $credit
 * @property string $currency currency code (GBP, CAD, etc)
 * @property string|null $memo
 * @property array $tags
 * @property Journal $journal
 * @property Carbon $post_date
 * @property Carbon $updated_at
 * @property Carbon $created_at
 */
class JournalTransaction extends Model
{
    /**
     * @var string
     */
    protected $table = 'accounting_journal_transactions';

    /**
     * @var bool
     */
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array
     */
    protected $guarded=['id'];

    /**
     * @var array
     */
    protected $casts = [
        'post_date' => 'datetime',
        'tags' => 'array',
    ];

    /**
     * Boot.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $transaction) {
            $transaction->id = (string)Str::orderedUuid();
        });

        // @todo make these asychronous so the balance can be updated in the background,
        // and event driven so it can be used or not.
        // The job can also be set up to queue only if a copy of the job is not already
        // queued for the same journal.

        static::saved(function (self $transaction) {
            $transaction->journal->resetCurrentBalances();
        });

        static::deleted(function (self $transaction) {
            $transaction->journal->resetCurrentBalances();
        });
    }

    /**
     * Journal relation.
     */
    public function journal()
    {
        return $this->belongsTo(config('accounting.model-classes.journal'));
    }

    /**
     * Set reference object.
     *
     * @deprecated use the reference relation instead.
     *
     * @param Model $object
     * @return JournalTransaction
     */
    public function referencesObject($object)
    {
        $this->reference_type = $object->getMorphClass();
        $this->reference_id = $object->id;
        $this->save();

        return $this;
    }

    /**
     * Reference the related object as a polymorphic relation.
     *
     * To associate a model with a transaction, use:
     *      $transaction->reference()->associate($model);
     *
     * @return MorphTo
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Set currency.
     *
     * @deprecated The currency [code] is a column attribute, so can be handled through
     * the usual Eloquent mechanisms.
     *
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

    public function getCreditAttribute(): ?Money
    {
        if ($this->attributes['credit'] !== null) {
            return new Money($this->attributes['credit'], new Currency($this->currency));
        }
        return null;
    }

    public function getDebitAttribute(): ?Money
    {
        if ($this->attributes['debit'] !== null) {
            return new Money($this->attributes['debit'], new Currency($this->currency));
        }
        return null;
    }
}
