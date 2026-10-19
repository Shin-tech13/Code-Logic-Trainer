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

    // The professor who created the classroom
    public function professor()
    {
        return $this->belongsTo(User::class, 'professor_id');
    }

    // Students enrolled in this classroom
    public function students()
    {
        return $this->belongsToMany(User::class, 'classroom_student', 'classroom_id', 'student_id');
    }

    // Files uploaded for this classroom
    public function uploadedFiles()
    {
        return $this->hasMany(UploadedFile::class);
    }
}
