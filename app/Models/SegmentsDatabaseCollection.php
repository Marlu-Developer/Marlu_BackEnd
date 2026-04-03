<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class SegmentsDatabaseCollection extends Model
{
    protected $collection = 'segments_database_collection';
    protected $connection = 'mongodb';
}
