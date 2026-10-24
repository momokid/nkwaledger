<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanReminder extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['loan_id'];
}
