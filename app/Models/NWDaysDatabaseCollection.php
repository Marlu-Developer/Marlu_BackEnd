<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class NWDaysDatabaseCollection extends Model
{
    protected $collection = 'nwdays_database_collection';
    protected $connection = 'mongodb';
}
