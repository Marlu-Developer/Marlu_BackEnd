<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class EmployeesDatabaseCollection extends Model
{
    protected $collection = 'employees_database_collection';
    protected $connection = 'mongodb';
}
