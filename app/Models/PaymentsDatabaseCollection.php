<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class PaymentsDatabaseCollection extends Model
{
    protected $collection = 'payments_database_collection';
    protected $connection = 'mongodb';
}
