<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class SettersDefaultConfigDatabaseCollection extends Model
{
    protected $collection = 'setters_default_config_database_collection';
    protected $connection = 'mongodb';

    protected $fillable = [
        'WeekDay_Name',
        'Slot1_Enabled',
        'Slot1_From',
        'Slot1_To',
        'Slot1_Setters',
        'Slot2_Enabled',
        'Slot2_From',
        'Slot2_To',
        'Slot2_Setters',
        'Slot3_Enabled',
        'Slot3_From',
        'Slot3_To',
        'Slot3_Setters',
        'Slot4_Enabled',
        'Slot4_From',
        'Slot4_To',
        'Slot4_Setters',
        'Slot5_Enabled',
        'Slot5_From',
        'Slot5_To',
        'Slot5_Setters',
    ];

    protected $casts = [
        'Slot1_Enabled' => 'boolean',
        'Slot2_Enabled' => 'boolean',
        'Slot3_Enabled' => 'boolean',
        'Slot4_Enabled' => 'boolean',
        'Slot5_Enabled' => 'boolean',
    ];
}
