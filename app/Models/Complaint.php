<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Complaint extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id', 'reporter_id', 'property_id', 'category', 'message', 'status', 'image',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function property()
    {
        return $this->belongsTo(Land::class, 'property_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'        => $this->id,
            'reporter'  => is_object($this->reporter) ? $this->reporter->full_name : null,
            'property'  => is_object($this->property) ? $this->property->name : null,
            'propertyId'=> $this->property_id,
            'category'  => $this->category,
            'message'   => $this->message,
            'image'     => $this->image,
            'status'    => $this->status,
            'date'      => $this->created_at?->toDateString(),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
