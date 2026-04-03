<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Jenssegers\Mongodb\Eloquent\Model;

class EmployeeSchedulesDatabaseCollection extends Model
{
    protected $collection = 'employee_schedules_database_collection';
    protected $connection = 'mongodb';

    protected $fillable = [
        'Employee_ID',
        'monday_enabled',
        'monday_start',
        'monday_end',
        'tuesday_enabled',
        'tuesday_start',
        'tuesday_end',
        'wednesday_enabled',
        'wednesday_start',
        'wednesday_end',
        'thursday_enabled',
        'thursday_start',
        'thursday_end',
        'friday_enabled',
        'friday_start',
        'friday_end',
        'saturday_enabled',
        'saturday_start',
        'saturday_end',
        'sunday_enabled',
        'sunday_start',
        'sunday_end',
    ];

    protected $casts = [
        'monday_enabled' => 'boolean',
        'tuesday_enabled' => 'boolean',
        'wednesday_enabled' => 'boolean',
        'thursday_enabled' => 'boolean',
        'friday_enabled' => 'boolean',
        'saturday_enabled' => 'boolean',
        'sunday_enabled' => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeesDatabaseCollection::class, 'Employee_ID', 'Employee_ID');
    }
} 