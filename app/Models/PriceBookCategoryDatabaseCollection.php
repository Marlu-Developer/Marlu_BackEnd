<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class PriceBookCategoryDatabaseCollection extends Model
{
    protected $collection = 'pricebook_category_database_collection';
    protected $connection = 'mongodb';
}