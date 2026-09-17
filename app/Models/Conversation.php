<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversation extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'property_id', 'buyer_id', 'owner_id',
        'last_message', 'last_message_time',
        'unread_buyer', 'unread_owner',
    ];

    protected $casts = [
        'unread_buyer' => 'integer',
        'unread_owner' => 'integer',
        'created_at'   => 'datetime',
        'updated_at'   => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Land::class, 'property_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'conversation_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'              => $this->id,
            'propertyId'      => $this->property_id,
            'propertyTitle'   => $this->property?->name,
            'propertyImage'   => $this->property?->image,
            'propertyPrice'   => $this->property?->price,
            'propertyLocation'=> $this->property?->location,
            'propertyStatus'  => $this->property?->status,
            'buyerId'         => $this->buyer_id,
            'buyerName'       => $this->buyer?->full_name,
            'ownerId'         => $this->owner_id,
            'ownerName'       => $this->owner?->full_name,
            'lastMessage'     => $this->last_message ?? 'Percakapan dimulai',
            'lastMessageTime' => $this->last_message_time ?? '',
            'unreadBuyer'     => $this->unread_buyer,
            'unreadOwner'     => $this->unread_owner,
            'createdAt'       => $this->created_at?->toISOString(),
            'updatedAt'       => $this->updated_at?->toISOString(),
        ];
    }
}
