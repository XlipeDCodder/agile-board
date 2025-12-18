<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommentAttachment extends Model
{
    protected $fillable = [
        'comment_id',
        'file_path',
        'file_name',
        'mime_type',
        'size',
    ];

    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }
}
