<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'issue_id',
        'uploaded_by',
        'filename',
        'path',
        'mime_type',
        'size_bytes',
        'created_at',
    ];

    public function issue()
    {
        return $this->belongsTo(Issue::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
