<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\ClassroomEmailAssignment;
use App\Models\User;
use App\Support\LiveSessionRoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProfessorStudentController extends Controller
{
    public function __construct(private LiveSessionRoster $sessionRoster)
    {
    }

    public function index(Request $request): View
    {
        $professor = $this->authenticatedProfessor();
        $classrooms = $professor->classrooms()->orderBy('title')->get();
        $selectedClassroomId = $request->filled('classroom_id')
            ? $request->integer('classroom_id')
            : null;

        if ($selectedClassroomId !== null) {
            abort_unless($classrooms->contains('id', $selectedClassroomId), 404);
        }

        $studentQuery = $this->sessionRoster->managedStudents($professor)
            ->with(['enrolledClassrooms' => fn ($query) => $query
                ->where('classrooms.professor_id', $professor->id)
                ->orderBy('title')]);

        if ($selectedClassroomId !== null) {
            $studentQuery->whereHas('enrolledClassrooms', fn ($query) => $query
                ->where('classrooms.id', $selectedClassroomId)
                ->where('classrooms.professor_id', $professor->id));
        }

        $students = $studentQuery->orderBy('name')->get();
        $classAssignments = ClassroomEmailAssignment::query()
            ->whereIn('classroom_id', $classrooms->modelKeys())
            ->when($selectedClassroomId !== null, fn ($query) => $query->where('classroom_id', $selectedClassroomId))
            ->with('classroom:id,title')
            ->orderBy('email')
            ->get();
        $assignableStudents = $this->sessionRoster->selectableInvitees($professor);

        return view('professor.students.index', compact(
            'students',
            'classrooms',
            'classAssignments',
            'assignableStudents',
            'selectedClassroomId',
        ));
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

        $this->authenticatedProfessor()->classrooms()->create([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'class_code' => $classCode,
        ]);

        return back()->with('status', 'Class created.');
    }

    public function assignToClass(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'classroom_id' => ['required', 'integer'],
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $professor = $this->authenticatedProfessor();
        $classroom = $professor->classrooms()->find($validated['classroom_id']);
        abort_unless($classroom, 403, 'You can only assign students to your own classes.');

        $email = $this->sessionRoster->normalizeEmail($validated['email']);
        abort_unless($email !== null, 422);

        $student = User::query()
            ->whereRaw('LOWER(personal_email) = ?', [$email])
            ->first();
        $whitelistEntry = $professor->whitelistedStudents()
            ->where('status', 'active')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if ($student !== null && ! $student->isStudent()) {
            return back()->withErrors(['email' => 'Choose a student account or an active whitelist entry.'])->withInput();
        }

        if ($student !== null) {
            abort_unless($this->sessionRoster->manages($professor, $student), 403);
        } elseif ($whitelistEntry === null) {
            return back()->withErrors(['email' => 'Choose a student managed by you or an active whitelist entry.'])->withInput();
        }

        DB::transaction(function () use ($professor, $classroom, $email, $student) {
            $professorClassroomIds = $professor->classrooms()->pluck('id');

            ClassroomEmailAssignment::query()
                ->whereIn('classroom_id', $professorClassroomIds)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->delete();

            if ($student !== null) {
                $student->enrolledClassrooms()->detach($professorClassroomIds);
                $student->enrolledClassrooms()->syncWithoutDetaching([$classroom->id]);

                return;
            }

            $classroom->emailAssignments()->create(['email' => $email]);
        });

        $message = $student
            ? 'Student assigned to '.$classroom->title.'.'
            : 'Email assigned to '.$classroom->title.'. It will be linked when the student registers.';

        return redirect()
            ->route('professor.students.index', ['classroom_id' => $classroom->id])
            ->with('status', $message);
    }

    public function removeEmailAssignment(ClassroomEmailAssignment $assignment): RedirectResponse
    {
        $classroom = $assignment->classroom;
        abort_unless($classroom && $classroom->professor_id === Auth::id(), 403);

        $assignment->delete();

        return redirect()
            ->route('professor.students.index', ['classroom_id' => $classroom->id])
            ->with('status', 'Pre-registration class assignment removed.');
    }

    public function edit(User $student): View
    {
        $this->authorizeStudent($student);

        $classrooms = $this->authenticatedProfessor()->classrooms()->orderBy('title')->get();
        $assignedClassroomIds = $student->enrolledClassrooms()
            ->where('classrooms.professor_id', Auth::id())
            ->pluck('classrooms.id')
            ->all();

        return view('professor.students.edit', compact('student', 'classrooms', 'assignedClassroomIds'));
    }

    public function update(Request $request, User $student)
    {
        $this->authorizeStudent($student);
        $professor = $this->authenticatedProfessor();

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
            $classroom = $professor->classrooms()->find($validated['classroom_id']);
            abort_unless($classroom, 403, 'You can only assign students to your own classes.');
        }

        $student->forceFill([
            'name' => $validated['name'],
            'personal_email' => $validated['personal_email'],
            'age' => $validated['age'] ?? null,
            'year_level' => $validated['year_level'],
            'student_id' => $validated['student_id'],
        ])->save();

        $professorClassroomIds = $professor->classrooms()->pluck('id');
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

        abort_unless($this->sessionRoster->manages($this->authenticatedProfessor(), $student), 403);
    }

    private function authenticatedProfessor(): User
    {
        $professor = Auth::user();

        if (! ($professor instanceof User)) {
            abort(403);
        }

        return $professor;
    }
}
