<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class CompanyProfileDatabaseCollection extends Model
{
    protected $collection = 'company_profile_database_collection';
    protected $connection = 'mongodb';
}
