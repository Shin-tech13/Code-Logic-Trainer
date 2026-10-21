<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_whitelisted',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_whitelisted' => 'boolean',
        ];
    }

    /**
     * Quick role helpers.
     */
    public function isProfessor(): bool
    {
        return $this->role === 'professor';
    }

    public function isStudent(): bool
    {
        return $this->role !== 'professor';
    }

    /**
     * Classrooms created by this professor.
     */
    public function classrooms()
    {
        return $this->hasMany(Classroom::class, 'professor_id');
    }

    /**
     * Students this professor has whitelisted.
     */
    public function whitelistedStudents()
    {
        return $this->hasMany(WhitelistedStudent::class, 'professor_id');
    }

    /**
     * Quizzes created by this professor.
     */
    public function quizzes()
    {
        return $this->hasMany(Quiz::class, 'professor_id');
    }

    /**
     * Live quiz sessions hosted by this professor.
     */
    public function hostedQuizSessions()
    {
        return $this->hasMany(QuizSession::class, 'professor_id');
    }

    /**
     * Live sessions this student participates in.
     */
    public function sessionParticipants()
    {
        return $this->hasMany(SessionParticipant::class, 'student_id');
    }
}
