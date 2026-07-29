<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotebookNote extends Model
{
    protected $fillable = [
        'user_id',
        'notebook_note_group_id',
        'title',
        'content',
        'content_type',
        'language',
        'is_important',
        'sort_order',
        'share_token',
    ];

    protected function casts(): array
    {
        return [
            'is_important' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(NotebookNoteGroup::class, 'notebook_note_group_id');
    }
}
