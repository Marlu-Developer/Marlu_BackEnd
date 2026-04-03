<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class EstimateDashboardLayoutDatabaseCollection extends Model
{
    protected $collection = 'estimate_dashboard_layout_database_collection';
    protected $connection = 'mongodb';
}
