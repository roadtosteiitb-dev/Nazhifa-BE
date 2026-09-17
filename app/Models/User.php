<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasUuids;

    protected $keyType  = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'id', 'full_name', 'email', 'password', 'user_type', 'photo', 'status', 'phone',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // JWTSubject interface
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'email'    => $this->email,
            'userType' => $this->user_type,
            'fullName' => $this->full_name,
        ];
    }

    // Relationships
    public function lands()
    {
        return $this->hasMany(Land::class, 'owner_id');
    }

    public function buyerConversations()
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function ownerConversations()
    {
        return $this->hasMany(Conversation::class, 'owner_id');
    }

    public function complaints()
    {
        return $this->hasMany(Complaint::class, 'reporter_id');
    }

    public function toApiArray(): array
    {
        return [
            'id'        => $this->id,
            'name'      => $this->full_name,
            'email'     => $this->email,
            'role'      => $this->user_type,
            'status'    => $this->status,
            'phone'     => $this->phone,
            'photo'     => $this->photo,
            'joinDate'  => $this->created_at?->toDateString(),
        ];
    }
}
