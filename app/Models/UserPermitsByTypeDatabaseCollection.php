<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class UserPermitsByTypeDatabaseCollection extends Model
{
    protected $collection = 'user_permits_by_type_database_collection';
    protected $connection = 'mongodb';
}
