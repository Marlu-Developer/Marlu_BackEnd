<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class WagesDatabaseCollection extends Model
{
    protected $collection = 'wages_database_collection';
    protected $connection = 'mongodb';
}
