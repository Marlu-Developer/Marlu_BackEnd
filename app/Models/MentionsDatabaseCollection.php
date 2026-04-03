<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class MentionsDatabaseCollection extends Model
{
    protected $collection = 'mentions_database_collection';
    protected $connection = 'mongodb';
}