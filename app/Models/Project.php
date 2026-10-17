<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'key',
        'description',
        'type',
        'lead_id',
        'is_archived',
        'organization_id',
    ];
    
}
