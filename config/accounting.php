<?php

return [
    'base_currency' => 'USD',

    'model-classes' => [
        'journal' => \Scottlaurent\Accounting\Models\Journal::class,
        'journal-transaction' => \Scottlaurent\Accounting\Models\JournalTransaction::class,
        'ledger' => \Scottlaurent\Accounting\Models\Ledger::class,
    ],
];