<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    protected $fillable = [
        'name',
        'key',
        'slug',
        'description',
        'type',
        'lead_id',
        'is_archived',
        'organization_id',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function lead()
    {
        return $this->belongsTo(User::class, 'lead_id');
    }

    public function members()
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    public function boards()
    {
        return $this->hasMany(Board::class);
    }

    public function sprints()
    {
        return $this->hasMany(Sprint::class);
    }

    public function labels()
    {
        return $this->hasMany(Label::class);
    }
}
