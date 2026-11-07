<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'purpose',
        'email',
        'phone_number',
        'email_code_hash',
        'phone_code_hash',
        'channels',
        'attempts',
        'resends',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
