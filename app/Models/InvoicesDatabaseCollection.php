<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class InvoicesDatabaseCollection extends Model
{
    protected $collection = 'invoices_database_collection';
    protected $connection = 'mongodb';
}
