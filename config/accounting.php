<?php

return [
    'base_currency' => 'GBP',

    'model-classes' => [
        'ledger' => \Scottlaurent\Accounting\Models\Ledger::class,
        'journal' => \Scottlaurent\Accounting\Models\Journal::class,
        'journal-transaction' => \Scottlaurent\Accounting\Models\JournalTransaction::class,
    ],
];