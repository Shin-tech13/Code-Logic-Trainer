<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhitelistedStudent extends Model
{
    use HasFactory;

    protected $fillable = [
        'professor_id',
        'name',
        'email',
        'status',
    ];

    public function professor()
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    /**
     * Match a registered user (if any) to this whitelist entry.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
