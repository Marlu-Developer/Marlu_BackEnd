<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class ApisStatusDatabaseCollection extends Model
{
    protected $collection = 'apis_status_database_collection';
    protected $connection = 'mongodb';
}
