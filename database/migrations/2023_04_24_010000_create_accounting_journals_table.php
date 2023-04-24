<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_journals', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->unsignedBigInteger('ledger_id')->nullable();
            $table->foreign('ledger_id')->references('id')->on('accounting_ledgers');

            $table->bigInteger('balance');
            $table->string('currency', 3);

            // @todo need some indexes.
            $table->string('morphed_type', 60);
            $table->bigInteger('morphed_id')->unsigned();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_journals');
    }
};
