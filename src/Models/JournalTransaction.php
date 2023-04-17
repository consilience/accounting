<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class JournalTransaction
 *
 * @package Scottlaurent\Accounting
 * @property    string $journal_id
 * @property    int $debit
 * @property    int $credit
 * @property    string $currency
 * @property    string memo
 * @property    \Carbon\Carbon $post_date
 * @property    \Carbon\Carbon $updated_at
 * @property    \Carbon\Carbon $created_at
 */
class JournalTransaction extends Model
{

    /**
     * @var string
     */
    protected $table = 'accounting_journal_transactions';

    /**
     * Currency.
     *
     * @todo this is a currency *code* - change the name or hint to Currency.
     * @todo also, should it not be an attribute, since it is a column in the database?
     *
     * @var string $currency
     */
    protected $currency;

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
        static::creating(function ($transaction) {
            // @todo Laravel will give UUIDs out of the box now. Use that instead.
            $transaction->id = \Ramsey\Uuid\Uuid::uuid4()->toString();
        });

    //    static::saved(function ($transaction) {
    //        $transaction->journal->resetCurrentBalances();
    //    });

        static::deleted(function ($transaction) {
            $transaction->journal->resetCurrentBalances();
        });

        // parent::boot();
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
     * @param Model $object
     * @return JournalTransaction
     */
    public function referencesObject($object)
    {
        $this->reference_type = get_class($object);
        $this->reference_id = $object->id;
        $this->save();
        return $this;
    }

    /**
     * Get reference object.
     *
     * @return \Illuminate\Database\Eloquent\Collection|Model|Model[]|null
     */
    public function getReferencedObject()
    {
        /**
         * @var Model $_class
         */
        $_class = new $this->reference_type;
        return $_class->find($this->reference_id);
    }

    /**
     * Set currency.
     *
     * @param string $currency
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
    }

}
