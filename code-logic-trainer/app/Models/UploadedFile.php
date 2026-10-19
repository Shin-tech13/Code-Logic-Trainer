<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'professor_id',
        'classroom_id',
        'file_path',
        'original_name',
        'status', // pending, processed, failed
    ];

    public function professor()
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function classroom()
    {
        return $this->belongsTo(Classroom::class);
    }
}
