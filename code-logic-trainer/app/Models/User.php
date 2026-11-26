<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = [
        'role' => 'student',
        'account_status' => 'approved',
        'is_whitelisted' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'personal_email',
        'password',
        'age',
        'year_level',
        'student_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
        'personal_email',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_whitelisted' => 'boolean',
            'approved_at' => 'datetime',
            'age' => 'integer',
        ];
    }

    public function isProfessor(): bool
    {
        return $this->role === 'professor';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStudent(): bool
    {
        return $this->role === 'student';
    }

    public function isApproved(): bool
    {
        return $this->account_status === 'approved';
    }

    public function routeNotificationForMail($notification = null): ?string
    {
        return $this->personal_email;
    }

    public function createdBy()
    {
        return $this->belongsTo(self::class, 'created_by_user_id');
    }

    public function createdUsers()
    {
        return $this->hasMany(self::class, 'created_by_user_id');
    }

    public function classrooms()
    {
        return $this->hasMany(Classroom::class, 'professor_id');
    }

    public function enrolledClassrooms()
    {
        return $this->belongsToMany(Classroom::class, 'classroom_student', 'student_id', 'classroom_id')
            ->withTimestamps();
    }

    public function whitelistedStudents()
    {
        return $this->hasMany(WhitelistedStudent::class, 'professor_id');
    }

    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'professor_id');
    }

    public function hostedQuizSessions()
    {
        return $this->hasMany(QuizSession::class, 'professor_id');
    }

    public function sessionParticipants()
    {
        return $this->hasMany(SessionParticipant::class, 'student_id');
    }
}
