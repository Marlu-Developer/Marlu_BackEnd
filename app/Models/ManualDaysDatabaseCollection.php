<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class ManualDaysDatabaseCollection extends Model
{
    protected $collection = 'manual_days_database_collection';
    protected $connection = 'mongodb';
}
