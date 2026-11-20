<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class CodeLogicLoginAddress
{
    public function generate(string $name): string
    {
        $localPart = Str::lower(Str::ascii($name));
        $localPart = preg_replace('/[^a-z0-9]+/', '', $localPart) ?: 'student';
        $localPart = substr($localPart, 0, 241);
        $candidate = $localPart.'@codelogic.com';
        $suffix = 2;

        while (User::where('email', $candidate)->exists()) {
            $suffixString = (string) $suffix++;
            $candidate = substr($localPart, 0, 241 - strlen($suffixString))
                .$suffixString.'@codelogic.com';
        }

        return $candidate;
    }
}
