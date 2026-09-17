<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'conversation_id', 'sender_id', 'sender_role', 'sender_name', 'text', 'status',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function conversation()
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'             => $this->id,
            'conversationId' => $this->conversation_id,
            'senderId'       => $this->sender_id,
            'senderRole'     => $this->sender_role,
            'senderName'     => $this->sender_name,
            'text'           => $this->text,
            'status'         => $this->status,
            'time'           => $this->created_at?->format('H:i'),
            'createdAt'      => $this->created_at?->toISOString(),
        ];
    }
}
