<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    protected $fillable = [
        'project_id',
        'board_column_id',
        'sprint_id',
        'parent_id',
        'code',
        'title',
        'description',
        'type',
        'status',
        'priority',
        'story_points',
        'reporter_id',
        'due_date',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function boardColumn()
    {
        return $this->belongsTo(BoardColumn::class);
    }

    public function sprint()
    {
        return $this->belongsTo(Sprint::class);
    }

    public function parent()
    {
        return $this->belongsTo(Issue::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Issue::class, 'parent_id');
    }

    public function assignees()
    {
        return $this->belongsToMany(User::class, 'issue_assignees');
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class, 'issue_labels');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function attachments()
    {
        return $this->hasMany(Attachment::class);
    }
}
