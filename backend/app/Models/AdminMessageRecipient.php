<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminMessageRecipient extends Model
{
    protected $fillable = [
        'admin_message_id',
        'recipient_id',
        'status',
        'read_at',
        'status_changed_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'status_changed_at' => 'datetime',
    ];

    public function message()
    {
        return $this->belongsTo(AdminMessage::class, 'admin_message_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}