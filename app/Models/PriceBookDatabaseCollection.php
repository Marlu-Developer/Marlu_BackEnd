<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class PriceBookDatabaseCollection extends Model
{
    protected $collection = 'price_book_database_collection';
    protected $connection = 'mongodb';
}