<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class EstimatesDatabaseCollection extends Model
{
    protected $collection = 'estimates_database_collection';
    protected $connection = 'mongodb';
}
