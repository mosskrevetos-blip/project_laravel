<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminMessage extends Model
{
    protected $fillable = [
        'sender_id',
        'subject',
        'body',
        'is_broadcast',
        'sent_at',
        'updated_content_at',
        'deleted_at_by_sender',
    ];

    protected $casts = [
        'is_broadcast' => 'boolean',
        'sent_at' => 'datetime',
        'updated_content_at' => 'datetime',
        'deleted_at_by_sender' => 'datetime',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipients()
    {
        return $this->hasMany(AdminMessageRecipient::class, 'admin_message_id');
    }

    public function attachments()
    {
        return $this->hasMany(AdminMessageAttachment::class, 'admin_message_id');
    }
}