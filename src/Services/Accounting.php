<?php

declare(strict_types=1);

namespace Scottlaurent\Accounting\Services;

use Carbon\Carbon;
use Exception;
use Scottlaurent\Accounting\Models\Journal;
use Money\Money;
use Money\Currency;
use Scottlaurent\Accounting\Exceptions\{InvalidJournalEntryValue,
    InvalidJournalMethod,
    DebitsAndCreditsDoNotEqual,
    TransactionCouldNotBeProcessed
};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Accounting
{
    /**
     * @var array
     */
    protected $transactionsPending = [];

    public static function newDoubleEntryTransactionGroup(): Accounting
    {
        return new self;
    }

    /**
     * @param Journal $journal
     * @param string $method 'credit' or 'debit'
     * @param Money $money The amount of money to credit or debit.
     * @param string|null $memo
     * @param null $referenced_object
     * @param Carbon|null $postdate
     * @throws InvalidJournalEntryValue
     * @throws InvalidJournalMethod
     * @internal param int $value
     */
    function addTransaction(
        Journal $journal,
        string $method,
        Money $money,
        string $memo = null,
        $referenced_object = null,
        Carbon $postdate = null
    ): void {

        if (!in_array($method, ['credit', 'debit'])) {
            throw new InvalidJournalMethod;
        }

        if ($money->getAmount() <= 0) {
            throw new InvalidJournalEntryValue();
        }

        $this->transactionsPending[] = [
            'journal' => $journal,
            'method' => $method,
            'money' => $money,
            'memo' => $memo,
            'referenced_object' => $referenced_object,
            'postdate' => $postdate
        ];
    }

    function transactionsPending(): array
    {
        return $this->transactionsPending;
    }

    /**
     * Save a transaction group.
     *
     * @return string
     */
    public function commit(): string
    {
        $this->assertTransactionCreditsEqualDebits();

        try {
            return DB::transaction(function () {
                $transactionGroupUuid = (string)Str::orderedUuid();

                foreach ($this->transactionsPending as $transaction_pending) {
                    $transaction = $transaction_pending['journal']->{$transaction_pending['method']}(
                        $transaction_pending['money'],
                        $transaction_pending['memo'],
                        $transaction_pending['postdate'],
                        $transactionGroupUuid,
                    );

                    if ($object = $transaction_pending['referenced_object']) {
                        $transaction->reference()->associate($object);
                    }
                }

                return $transactionGroupUuid;
            });

        } catch (Exception $e) {
            throw new TransactionCouldNotBeProcessed('Rolling Back Database. Message: ' . $e->getMessage());
        }
    }

    /**
     * @throws DebitsAndCreditsDoNotEqual
     */
    private function assertTransactionCreditsEqualDebits(): void
    {
        $credits = 0;
        $debits = 0;

        foreach ($this->transactionsPending as $transaction_pending) {
            if ($transaction_pending['method'] == 'credit') {
                $credits += $transaction_pending['money']->getAmount();
            } else {
                $debits += $transaction_pending['money']->getAmount();
            }
        }

        if ($credits !== $debits) {
            throw new DebitsAndCreditsDoNotEqual('In this transaction, credits == ' . $credits . ' and debits == ' . $debits);
        }
    }
}
