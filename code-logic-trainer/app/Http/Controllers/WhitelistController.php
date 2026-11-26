<?php

namespace App\Http\Controllers;

use App\Models\WhitelistedStudent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class WhitelistController extends Controller
{
    public function index()
    {
        $students = WhitelistedStudent::where('professor_id', Auth::id())
            ->with('user:id,email,personal_email,is_whitelisted')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('professor.whitelist', compact('students'));
    }

    // Accept one student or a bulk email list.
    public function store(Request $request)
    {
        $request->validate([
            'name'    => ['required_without_all:bulk,bulk_emails', 'string', 'max:255'],
            'email'   => ['required_without_all:bulk,bulk_emails', 'email', 'max:255'],
            'bulk'    => ['nullable', 'string'],
            'bulk_emails' => ['nullable', 'string'],
        ]);

        $entries = [];

        if ($request->filled('bulk') || $request->filled('bulk_emails')) {
            $raw = $request->filled('bulk_emails') ? $request->input('bulk_emails') : $request->input('bulk');
            foreach (preg_split('/[\s,;]+/', $raw) as $token) {
                $token = trim($token);
                if ($token === '') {
                    continue;
                }
                $entries[] = ['name' => $this->deriveName($token), 'email' => strtolower($token)];
            }
        } else {
            $entries[] = [
                'name'  => $request->input('name'),
                'email' => strtolower($request->input('email')),
            ];
        }

        $created = 0;
        $errors  = [];

        foreach ($entries as $entry) {
            if (! filter_var($entry['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email: {$entry['email']}";
                continue;
            }

            $exists = WhitelistedStudent::where('email', $entry['email'])->exists();
            if ($exists) {
                $errors[] = "{$entry['email']} is already whitelisted.";
                continue;
            }

            WhitelistedStudent::create([
                'professor_id' => Auth::id(),
                'name'         => $entry['name'],
                'email'        => $entry['email'],
                'status'       => 'active',
            ]);

            $this->syncUserWhitelistFlag($entry['email']);
            $created++;
        }

        return back()->with([
            'status' => $created > 0 ? "{$created} student(s) added to the whitelist." : null,
            'whitelist_errors' => $errors,
        ]);
    }

    public function destroy(WhitelistedStudent $student)
    {
        if ($student->professor_id !== Auth::id()) {
            abort(403);
        }

        $email = $student->email;
        $student->delete();

        $this->syncUserWhitelistFlag($email);

        return back()->with('status', "{$email} removed from the whitelist.");
    }

    // Keep the cached user flag aligned with the whitelist table.
    protected function syncUserWhitelistFlag(string $email): void
    {
        $stillWhitelisted = WhitelistedStudent::where('email', $email)->exists();
        User::where('personal_email', $email)->update(['is_whitelisted' => $stillWhitelisted]);
    }

    protected function deriveName(string $email): string
    {
        $local = strstr($email, '@', true) ?: $email;
        $local = str_replace(['.', '_', '-'], ' ', $local);

        return ucwords($local);
    }
}
