<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class EventsDatabaseCollection extends Model
{
    protected $collection = 'events_database_collection';
    protected $connection = 'mongodb';
}
