<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\AccountApproved;
use App\Services\AccountNotificationSender;
use App\Services\CodeLogicLoginAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(Request $request): View
    {
        $creatorRules = ['nullable'];
        if ($request->input('created_by') === 'system') {
            $creatorRules[] = Rule::in(['system']);
        } else {
            $creatorRules[] = 'integer';
            $creatorRules[] = Rule::exists('users', 'id');
        }

        $validated = $request->validate([
            'role' => ['nullable', Rule::in(['admin', 'professor', 'student'])],
            'status' => ['nullable', Rule::in(['pending', 'approved', 'suspended'])],
            'created_by' => $creatorRules,
        ]);

        $users = User::query()
            ->with('createdBy:id,name,email')
            ->when($validated['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('account_status', $status))
            ->when(
                ($validated['created_by'] ?? null) === 'system',
                fn ($query) => $query->whereNull('created_by_user_id')
            )
            ->when(
                isset($validated['created_by']) && $validated['created_by'] !== 'system',
                fn ($query) => $query->where('created_by_user_id', $validated['created_by'])
            )
            ->orderByRaw("CASE WHEN account_status = 'pending' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $creators = User::query()
            ->whereIn('role', ['admin', 'professor'])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('users.index', compact('users', 'creators'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(Request $request, CodeLogicLoginAddress $loginAddress)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'personal_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,personal_email'],
            'role' => ['required', Rule::in(['admin', 'professor', 'student'])],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = new User([
            'name' => $validated['name'],
            'email' => $loginAddress->generate($validated['name']),
            'personal_email' => $validated['personal_email'],
            'password' => $validated['password'],
        ]);
        $user->forceFill([
            'role' => $validated['role'],
            'account_status' => 'approved',
            'approved_by_user_id' => Auth::id(),
            'approved_at' => now(),
            'is_whitelisted' => $validated['role'] === 'student',
        ]);
        $user->createdBy()->associate(Auth::user());
        $user->save();

        return redirect()
            ->route('users.index')
            ->with('status', ucfirst($user->role).' account created. Login address: '.$user->email)
            ->with('code_logic_login', $user->email);
    }

    public function approve(User $user, AccountNotificationSender $notificationSender)
    {
        $actor = Auth::user();

        if ($actor->isProfessor() && ! $user->isStudent()) {
            abort(403, 'Professors may only approve student accounts.');
        }

        if ($actor->isProfessor() && ! $user->enrolledClassrooms()->where('classrooms.professor_id', $actor->id)->exists()) {
            return back()->with('error', 'Assign this student to one of your classes before approving the account.');
        }

        if ($user->account_status !== 'pending') {
            return back()->with('error', 'This account is no longer pending approval.');
        }

        DB::transaction(function () use ($user, $actor) {
            $user->forceFill([
                'account_status' => 'approved',
                'approved_by_user_id' => $actor->id,
                'approved_at' => now(),
                'is_whitelisted' => $user->isStudent() ? true : $user->is_whitelisted,
            ])->save();
        });

        $notificationSender->send($user, new AccountApproved, 'account approval');

        return back()->with('status', $user->name.' has been approved.');
    }

    public function updateRole(Request $request, User $user)
    {
        abort_if(Auth::id() === $user->id, 403, 'You cannot change your own role.');

        $validated = $request->validate([
            'role' => ['required', Rule::in(['admin', 'professor', 'student'])],
        ]);

        $user->forceFill([
            'role' => $validated['role'],
            'is_whitelisted' => $validated['role'] === 'student',
        ])->save();

        return back()->with('status', $user->name.'’s role has been updated.');
    }

    public function updateStatus(Request $request, User $user, AccountNotificationSender $notificationSender)
    {
        abort_if(Auth::id() === $user->id, 403, 'You cannot change your own account status.');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'suspended'])],
        ]);
        $wasApproved = $user->isApproved();

        $user->forceFill([
            'account_status' => $validated['status'],
            'approved_by_user_id' => $validated['status'] === 'approved' ? Auth::id() : $user->approved_by_user_id,
            'approved_at' => $validated['status'] === 'approved' ? now() : $user->approved_at,
            'is_whitelisted' => $validated['status'] === 'approved' && $user->isStudent()
                ? true
                : $user->is_whitelisted,
        ])->save();

        if (! $wasApproved && $user->isApproved()) {
            $notificationSender->send($user, new AccountApproved, 'account approval');
        }

        return back()->with('status', $user->name.'’s account status has been updated.');
    }
}
