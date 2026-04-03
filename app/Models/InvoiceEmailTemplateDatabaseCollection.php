<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class InvoiceEmailTemplateDatabaseCollection extends Model
{
    protected $collection = 'invoice_email_template_database_collection';
    protected $connection = 'mongodb';
}
