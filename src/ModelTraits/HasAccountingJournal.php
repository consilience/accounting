<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\ModelTraits;

/**
 * A model that has an accounting journal.
 */

use Illuminate\Database\Eloquent\Model;
use Scottlaurent\Accounting\Models\Journal;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Scottlaurent\Accounting\Exceptions\JournalAlreadyExists;

/**
 * @mixin Model
 * @property Journal $journal
 */
trait HasAccountingJournal
{
    public function journal(): MorphOne
    {
        return $this->morphOne(Journal::class, 'morphed');
    }

    /**
     * Initialize a new journal for this model instance.
     *
     * @todo accept currency code or model instance
     *
     * @param null|string $currency_code
     * @param null|string $ledger_id
     * @return mixed
     * @throws JournalAlreadyExists
     */
    public function initJournal(
        ?string $currency_code = null,
        ?string $ledger_id = null,
    )
    {
        if ($currency_code === null) {
            $currency_code = config('accounting.base_currency');
        }

        if (! $this->journal) {
            $journal = new Journal();

            $journal->ledger_id = $ledger_id;
            $journal->currency = $currency_code;
            $journal->balance = 0;

            return $this->journal()->save($journal);
        }

        throw new JournalAlreadyExists;
    }
}
