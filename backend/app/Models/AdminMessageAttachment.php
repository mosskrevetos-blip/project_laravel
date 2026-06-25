<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class AdminMessageAttachment extends Model
{
    protected $fillable = [
        'admin_message_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $appends = ['url'];

    public function message()
    {
        return $this->belongsTo(AdminMessage::class, 'admin_message_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}