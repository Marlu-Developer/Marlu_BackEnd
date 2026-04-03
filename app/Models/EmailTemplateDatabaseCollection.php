<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class EmailTemplateDatabaseCollection extends Model
{
    protected $collection = 'email_template_database_collection';
    protected $connection = 'mongodb';
}