<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Jenssegers\Mongodb\Eloquent\Model;

class InvoicePDFTemplateDatabaseCollection extends Model
{
    protected $collection = 'invoice_pdf_template_database_collection';
    protected $connection = 'mongodb';
}
