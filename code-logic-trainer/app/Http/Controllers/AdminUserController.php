<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            'created_by' => $creatorRules,
        ]);

        $users = User::query()
            ->with('createdBy:id,name,email')
            ->when($validated['role'] ?? null, fn ($query, $role) => $query->where('role', $role))
            ->when(
                ($validated['created_by'] ?? null) === 'system',
                fn ($query) => $query->whereNull('created_by_user_id')
            )
            ->when(
                isset($validated['created_by']) && $validated['created_by'] !== 'system',
                fn ($query) => $query->where('created_by_user_id', $validated['created_by'])
            )
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

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['professor', 'student'])],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_whitelisted' => $validated['role'] === 'student',
        ]);
        $user->createdBy()->associate(Auth::user());
        $user->save();

        return redirect()
            ->route('users.index')
            ->with('status', ucfirst($user->role) . ' account created successfully.');
    }
}
