<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class CronJobsStatusDatabaseCollection extends Model
{
    protected $collection = 'cron_jobs_status_database_collection';
    protected $connection = 'mongodb';
    
    protected $fillable = [
        'job_id',
        'job_type', // 'cloud' or 'email'
        'active',
        'description',
        'filteredBy',
        'filterValue',
        'updatingFrequency',
        'updatingTime',
        'variables',
        'lastUpdateSize',
        'lastUpdateFinishTime',
        'systemAnswer',
        'sheetUrl', // for cloud jobs
        'email', // for email jobs
        'emailSubject', // for email jobs
        'fileName', // for email jobs
    ];
    
    protected $casts = [
        'active' => 'boolean',
    ];
}
