<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class DailyScheduleDatabaseCollection extends Model
{
    protected $collection = 'daily_schedule_database_collection';
    protected $connection = 'mongodb';
}
