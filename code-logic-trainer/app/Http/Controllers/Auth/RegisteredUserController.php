<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\WhitelistedStudent;
use App\Models\User;
use App\Notifications\AccountPendingApproval;
use App\Services\AccountNotificationSender;
use App\Services\CodeLogicLoginAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Show the one-time registration confirmation.
     */
    public function confirmation(Request $request): View|RedirectResponse
    {
        $loginAddress = $request->session()->pull('registration_login_address');

        if (! is_string($loginAddress) || $loginAddress === '') {
            return redirect()->route('login');
        }

        return view('auth.registration-confirmation', [
            'loginAddress' => $loginAddress,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(
        Request $request,
        CodeLogicLoginAddress $loginAddress,
        AccountNotificationSender $notificationSender
    ): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'personal_email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,personal_email'],
            'year_level' => ['required', 'string', 'max:50'],
            'student_id' => ['required', 'string', 'max:100', 'unique:users,student_id'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = DB::transaction(function () use ($validated, $loginAddress) {
            $whitelistEntry = WhitelistedStudent::where('email', $validated['personal_email'])->first();
            $user = new User([
                'name' => $validated['name'],
                'email' => $loginAddress->generate($validated['name']),
                'personal_email' => $validated['personal_email'],
                'year_level' => $validated['year_level'],
                'student_id' => $validated['student_id'],
                'password' => $validated['password'],
            ]);
            $user->forceFill([
                'role' => 'student',
                'account_status' => 'pending',
                'is_whitelisted' => $whitelistEntry !== null,
            ]);

            if ($whitelistEntry) {
                $user->createdBy()->associate($whitelistEntry->professor_id);
            }

            $user->save();

            return $user;
        });

        $notificationSender->send($user, new AccountPendingApproval, 'registration confirmation');

        return redirect()
            ->route('register.confirmation')
            ->with('registration_login_address', $user->email);
    }
}
