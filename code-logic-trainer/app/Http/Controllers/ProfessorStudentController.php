<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\User;
use App\Models\WhitelistedStudent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfessorStudentController extends Controller
{
    public function index(): View
    {
        $professor = Auth::user();
        $whitelistedEmails = WhitelistedStudent::query()
            ->where('professor_id', $professor->id)
            ->where('status', 'active')
            ->pluck('email');

        $students = User::query()
            ->where('role', 'student')
            ->where(function ($query) use ($professor, $whitelistedEmails) {
                $query->where('created_by_user_id', $professor->id)
                    ->orWhereIn('personal_email', $whitelistedEmails)
                    ->orWhereHas('enrolledClassrooms', fn ($classroomQuery) => $classroomQuery
                        ->where('classrooms.professor_id', $professor->id));
            })
            ->with(['enrolledClassrooms' => fn ($query) => $query
                ->where('classrooms.professor_id', $professor->id)
                ->orderBy('title')])
            ->orderBy('name')
            ->get();

        $classrooms = $professor->classrooms()->orderBy('title')->get();

        return view('professor.students.index', compact('students', 'classrooms'));
    }

    public function storeClassroom(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        do {
            $classCode = Str::upper(Str::random(4)).'-'.random_int(1000, 9999);
        } while (Classroom::where('class_code', $classCode)->exists());

        Auth::user()->classrooms()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'class_code' => $classCode,
        ]);

        return back()->with('status', 'Class created.');
    }

    public function edit(User $student): View
    {
        $this->authorizeStudent($student);

        $classrooms = Auth::user()->classrooms()->orderBy('title')->get();
        $assignedClassroomIds = $student->enrolledClassrooms()
            ->where('classrooms.professor_id', Auth::id())
            ->pluck('classrooms.id')
            ->all();

        return view('professor.students.edit', compact('student', 'classrooms', 'assignedClassroomIds'));
    }

    public function update(Request $request, User $student)
    {
        $this->authorizeStudent($student);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'personal_email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'personal_email')->ignore($student->id),
            ],
            'age' => ['nullable', 'integer', 'min:1', 'max:120'],
            'year_level' => ['required', 'string', 'max:50'],
            'student_id' => [
                'required',
                'string',
                'max:100',
                Rule::unique(User::class, 'student_id')->ignore($student->id),
            ],
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')],
        ]);

        $classroom = null;
        if (! empty($validated['classroom_id'])) {
            $classroom = Auth::user()->classrooms()->find($validated['classroom_id']);
            abort_unless($classroom, 403, 'You can only assign students to your own classes.');
        }

        $student->forceFill([
            'name' => $validated['name'],
            'personal_email' => $validated['personal_email'],
            'age' => $validated['age'] ?? null,
            'year_level' => $validated['year_level'],
            'student_id' => $validated['student_id'],
        ])->save();

        $professorClassroomIds = Auth::user()->classrooms()->pluck('id');
        $student->enrolledClassrooms()->detach($professorClassroomIds);
        if ($classroom) {
            $student->enrolledClassrooms()->syncWithoutDetaching([$classroom->id]);
        }

        return redirect()
            ->route('professor.students.edit', $student)
            ->with('status', 'Student details updated.');
    }

    private function authorizeStudent(User $student): void
    {
        abort_unless($student->isStudent(), 404);

        $professor = Auth::user();
        $isCreator = $student->created_by_user_id === $professor->id;
        $isWhitelisted = WhitelistedStudent::query()
            ->where('professor_id', $professor->id)
            ->where('email', $student->personal_email)
            ->where('status', 'active')
            ->exists();
        $isInClass = $student->enrolledClassrooms()
            ->where('classrooms.professor_id', $professor->id)
            ->exists();

        abort_unless($isCreator || $isWhitelisted || $isInClass, 403);
    }
}
