<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class JobDashboardLayoutDatabaseCollection extends Model
{
    protected $collection = 'job_dashboard_layout_database_collection';
    protected $connection = 'mongodb';
}
