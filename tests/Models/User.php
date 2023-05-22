<?php

namespace Models;

use Illuminate\Database\Eloquent\Model;
use Scottlaurent\Accounting\ModelTraits\HasAccountingJournal;

/**
 * Class User
 *
 * NOTE: This is only used for testing purposes.  It's not required for us
 *
 * @property    int                     $id
 * @property 	HasAccountingJournal		$journal
 *
 */
class User extends Model
{
	use HasAccountingJournal;
}


