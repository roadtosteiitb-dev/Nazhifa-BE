<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Land extends Model
{
    protected $keyType      = 'string';
    public    $incrementing = false;

    protected $fillable = [
        'name', 'location', 'price', 'is_for_sale', 'type', 'status',
        'owner_id', 'description', 'image', 'images',
        'floors', 'bedrooms', 'bathrooms', 'electricity',
        'certificate', 'certificate_image', 'garage',
        'unit_floor', 'unit_type', 'furnished',
        'land_area', 'building_area', 'facilities',
        'views', 'favorites', 'inquiries_count',
        'rejection_reason',
    ];

    protected $casts = [
        'is_for_sale'  => 'boolean',
        'price'           => 'integer',
        'views'           => 'integer',
        'favorites'       => 'integer',
        'inquiries_count' => 'integer',
        'created_at'      => 'datetime',
        'updated_at'      => 'datetime',
    ];

    public function getImagesAttribute($value): array
    {
        if (is_array($value)) return $value;
        if (empty($value)) return [];
        if (is_string($value) && str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $trimmed = trim($value, '{}');
            if ($trimmed === '') return [];
            return array_map(fn($v) => trim($v, '"\\'), str_getcsv($trimmed));
        }
        return is_string($value) ? (json_decode($value, true) ?? []) : [];
    }

    public function getFacilitiesAttribute($value): array
    {
        if (is_array($value)) return $value;
        if (empty($value)) return [];
        if (is_string($value) && str_starts_with($value, '{') && str_ends_with($value, '}')) {
            $trimmed = trim($value, '{}');
            if ($trimmed === '') return [];
            return array_map(fn($v) => trim($v, '"\\'), str_getcsv($trimmed));
        }
        return is_string($value) ? (json_decode($value, true) ?? []) : [];
    }

    // Relationship
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class, 'property_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'property_id');
    }

    // AWS's wildcard TLS cert for virtual-hosted-style S3 URLs
    // (*.s3.<region>.amazonaws.com) only covers a single subdomain label.
    // Bucket names containing a dot (e.g. "cdn.titikhuni") add an extra
    // label, so HTTPS to https://cdn.titikhuni.s3.<region>.amazonaws.com/...
    // fails TLS hostname verification for every client, RN included.
    // Rewriting to path-style (https://s3.<region>.amazonaws.com/<bucket>/...)
    // points at the exact same object over a hostname the cert actually covers.
    public static function fixS3Url(?string $url): ?string
    {
        if (!$url) return $url;

        $pattern = '#^https://([a-z0-9.-]+)\.s3\.([a-z0-9-]+)\.amazonaws\.com/(.*)$#i';
        if (preg_match($pattern, $url, $m) && str_contains($m[1], '.')) {
            return "https://s3.{$m[2]}.amazonaws.com/{$m[1]}/{$m[3]}";
        }

        return $url;
    }

    // Helper: format for API response
    public function toApiArray(?float $distanceKm = null): array
    {
        return [
            'id'               => $this->id,
            'name'             => $this->name,
            'location'         => $this->location,
            'price'            => $this->price,
            'isForSale'        => $this->is_for_sale,
            'type'             => $this->type,
            'status'           => $this->status,
            'ownerId'          => $this->owner_id,
            'owner'            => is_object($this->owner) ? $this->owner->full_name : ($this->owner ?? null),
            'description'      => $this->description,
            'image'            => self::fixS3Url($this->image),
            'images'           => array_map([self::class, 'fixS3Url'], $this->images ?? []),
            'area'             => [
                'land'     => $this->land_area,
                'building' => $this->building_area,
            ],
            'floors'           => $this->floors,
            'bedrooms'         => $this->bedrooms,
            'bathrooms'        => $this->bathrooms,
            'electricity'      => $this->electricity,
            'certificate'      => $this->certificate,
            'certificateImage' => self::fixS3Url($this->certificate_image),
            'garage'           => $this->garage,
            'unitFloor'        => $this->unit_floor,
            'unitType'         => $this->unit_type,
            'furnished'        => $this->furnished,
            'facilities'       => $this->facilities ?? [],
            'views'            => $this->views,
            'favorites'        => $this->favorites,
            'inquiriesCount'   => $this->inquiries_count,
            'center'           => $this->latitude !== null ? [
                'latitude'  => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
            ] : null,
            'distanceKm'       => $distanceKm,
            'rejectionReason'  => $this->rejection_reason,
            'createdAt'        => $this->created_at?->toISOString(),
            'updatedAt'        => $this->updated_at?->toISOString(),
        ];
    }
}
