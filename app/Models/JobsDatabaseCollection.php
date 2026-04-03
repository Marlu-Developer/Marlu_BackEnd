<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class JobsDatabaseCollection extends Model
{
    protected $collection = 'jobs_database_collection';
    protected $connection = 'mongodb';
}
