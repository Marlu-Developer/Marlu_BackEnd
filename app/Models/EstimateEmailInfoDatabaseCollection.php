<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class EstimateEmailInfoDatabaseCollection extends Model
{
    protected $collection = 'estimate_email_info_database_collection';
    protected $connection = 'mongodb';
}
