<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Scottlaurent\Accounting\ModelTraits\HasAccountingJournal;

/**
 * Class Account
 *
 * @property    int                     $id
 * @property 	string					$name
 *
 */
class Account extends Model
{
	use HasAccountingJournal;
}
