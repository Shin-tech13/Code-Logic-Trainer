<?php

namespace App\Support;

use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LiveSessionRoster
{
    public function managedStudents(User $professor): Builder
    {
        $whitelistedEmails = $professor->whitelistedStudents()
            ->where('status', 'active')
            ->selectRaw('LOWER(email)');

        return User::query()
            ->where('role', 'student')
            ->where(function (Builder $query) use ($professor, $whitelistedEmails) {
                $query->where('created_by_user_id', $professor->id)
                    ->orWhereIn(DB::raw('LOWER(personal_email)'), $whitelistedEmails)
                    ->orWhereHas('enrolledClassrooms', fn (Builder $classrooms) => $classrooms
                        ->where('classrooms.professor_id', $professor->id));
            });
    }

    public function manages(User $professor, User $student): bool
    {
        if (! $student->isStudent()) {
            return false;
        }

        if ((int) $student->created_by_user_id === $professor->id) {
            return true;
        }

        $email = $this->normalizeEmail($student->personal_email);
        $isWhitelisted = $email !== null && $professor->whitelistedStudents()
            ->where('status', 'active')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();
        $isInClass = $student->enrolledClassrooms()
            ->where('classrooms.professor_id', $professor->id)
            ->exists();

        return $isWhitelisted || $isInClass;
    }

    public function isEligible(QuizSession $session, User $student): bool
    {
        if ($session->roster_mode === null) {
            return $student->is_whitelisted;
        }

        $email = $this->normalizeEmail($student->personal_email);

        return $session->invitees()
            ->where(function (Builder $query) use ($student, $email) {
                $query->where('user_id', $student->id);

                if ($email !== null) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }
            })
            ->exists();
    }

    public function selectableInvitees(User $professor): Collection
    {
        $whitelisted = $professor->whitelistedStudents()
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['name', 'email'])
            ->keyBy(fn ($entry) => $this->normalizeEmail($entry->email));

        $students = $this->managedStudents($professor)
            ->with(['enrolledClassrooms' => fn ($query) => $query
                ->where('classrooms.professor_id', $professor->id)
                ->orderBy('title')])
            ->whereNotNull('personal_email')
            ->orderBy('name')
            ->get(['id', 'name', 'personal_email']);

        $options = [];

        foreach ($students as $student) {
            $email = $this->normalizeEmail($student->personal_email);
            if ($email === null) {
                continue;
            }

            $classNames = $student->enrolledClassrooms->pluck('title')->all();
            $context = $classNames === [] ? 'Managed student' : implode(', ', $classNames);
            if ($whitelisted->has($email)) {
                $context .= ' · Whitelisted';
            }

            $options[$email] = [
                'name' => $student->name,
                'email' => $email,
                'context' => $context,
            ];
        }

        foreach ($whitelisted as $email => $entry) {
            if (! isset($options[$email])) {
                $options[$email] = [
                    'name' => $entry->name,
                    'email' => $email,
                    'context' => 'Whitelist',
                ];
            }
        }

        return collect($options)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();
    }

    public function normalizeEmail(?string $email): ?string
    {
        $normalized = strtolower(trim((string) $email));

        return $normalized === '' ? null : $normalized;
    }
}
