<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\UploadedFile;

class Classroom extends Model
{
    use HasFactory;

    protected $fillable = [
        'professor_id',
        'title',
        'class_code',
        'description',
    ];

    public function professor()
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'classroom_student', 'classroom_id', 'student_id');
    }

    public function uploadedFiles()
    {
        return $this->hasMany(UploadedFile::class);
    }
}
