<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\ModelTraits;

/**
 * Trait for models that have journal transactions referencing them.
 */

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Scottlaurent\Accounting\Models\JournalTransaction;

/**
 * @mixin Model
 * @property Collection<JournalTransaction> $journalTransactions
 */
trait IsJournalTransactionReference
{
    /**
     * A model may have journal transactions referencing it.
     *
     * @return MorphMany
     */
    public function journalTransactions(): MorphMany
    {
        return $this->morphMany(JournalTransaction::class, 'reference');
    }
}
