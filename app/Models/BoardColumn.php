<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BoardColumn extends Model
{
    protected $fillable = [
        'board_id',
        'name',
        'mapped_status',
        'position',
    ];

    public function board()
    {
        return $this->belongsTo(Board::class);
    }

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }
}
