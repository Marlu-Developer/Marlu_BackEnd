<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class PDFTemplateDatabaseCollection extends Model
{
    protected $collection = 'pdf_template_database_collection';
    protected $connection = 'mongodb';
}