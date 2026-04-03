<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class ReadySegmentNotifyDatabaseCollection extends Model
{
    protected $collection = 'ready_segment_notify_database_collection';
    protected $connection = 'mongodb';
}
