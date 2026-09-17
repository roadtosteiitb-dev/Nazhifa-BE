<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'property_id', 'property_name', 'type', 'owner_id', 'reason', 'is_read',
    ];

    protected $casts = [
        'is_read'    => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function property()
    {
        return $this->belongsTo(Land::class, 'property_id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'           => $this->id,
            'propertyId'   => $this->property_id,
            'propertyName' => $this->property_name,
            'type'         => $this->type,
            'ownerId'      => $this->owner_id,
            'reason'       => $this->reason,
            'isRead'       => $this->is_read,
            'createdAt'    => $this->created_at?->toISOString(),
        ];
    }
}
