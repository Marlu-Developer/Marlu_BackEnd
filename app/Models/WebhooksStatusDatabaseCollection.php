<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class WebhooksStatusDatabaseCollection extends Model
{
    protected $collection = 'webhooks_status_database_collection';
    protected $connection = 'mongodb';
}