<?php
namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IdentityNormalizerService
{
    public function normalizeEmail(?string $email): ?string
    {
        if ($email === null) return null;
        $email = trim(Str::lower($email));
        return $email === '' ? null : $email;
    }

    /**
     * Scratchgard policy: national mobile number is exactly 10 digits.
     * Country code is stored separately and canonical mobile is E.164-like (+<cc><10 digits>).
     */
    public function normalizeMobile(?string $mobile, ?string $countryCode = null, string $defaultCountryCode = '+91'): array
    {
        $raw = trim((string)$mobile);
        if ($raw === '') return ['country_code'=>null,'national_number'=>null,'canonical'=>null];

        $digits = preg_replace('/\D+/', '', $raw);
        $cc = preg_replace('/\D+/', '', (string)$countryCode);
        $defaultCc = preg_replace('/\D+/', '', $defaultCountryCode) ?: '91';

        if ($cc !== '') {
            if (!preg_match('/^\d{1,4}$/', $cc)) {
                throw ValidationException::withMessages(['mobile_country_code'=>'Country code must contain 1 to 4 digits.']);
            }
            // If user pasted full number including same country code, strip it.
            if (strlen($digits) > 10 && str_starts_with($digits, $cc)) $digits = substr($digits, strlen($cc));
            if (strlen($digits) !== 10) {
                throw ValidationException::withMessages(['mobile'=>'Enter exactly 10 mobile digits after the country code.']);
            }
        } else {
            if (strlen($digits) === 10) {
                $cc = $defaultCc;
            } elseif (strlen($digits) > 10 && strlen($digits) <= 14) {
                $national = substr($digits, -10);
                $prefix = substr($digits, 0, -10);
                if ($prefix === '' || strlen($prefix) > 4) {
                    throw ValidationException::withMessages(['mobile'=>'Enter a valid country code followed by exactly 10 mobile digits.']);
                }
                $cc = $prefix;
                $digits = $national;
            } else {
                throw ValidationException::withMessages(['mobile'=>'Enter exactly 10 mobile digits. Country code is optional.']);
            }
        }

        return [
            'country_code' => '+'.$cc,
            'national_number' => $digits,
            'canonical' => '+'.$cc.$digits,
        ];
    }

    public function normalizeUsername(?string $username): ?string
    {
        if ($username === null) return null;
        $username = trim(Str::lower($username));
        $username = preg_replace('/\s+/', '.', $username);
        $username = preg_replace('/[^a-z0-9._]/', '', $username);
        $username = preg_replace('/[._]{2,}/', '.', $username);
        $username = trim($username, '._');
        return $username === '' ? null : $username;
    }

    public function usernameValid(string $username): bool
    {
        return (bool)preg_match('/^(?=.*[a-z])[a-z0-9][a-z0-9._]{2,28}[a-z0-9]$/', $username);
    }

    public function generateUsername(string $name): string
    {
        $base = $this->normalizeUsername(Str::ascii($name)) ?: 'user';
        $base = substr($base, 0, 22);
        if (strlen($base) < 4) $base = str_pad($base, 4, 'x');
        $candidate = $base;
        $i = 0;
        while (User::where('username', $candidate)->exists()) {
            $i++;
            $suffix = (string)random_int(1000, 9999);
            $candidate = substr($base, 0, max(4, 29 - strlen($suffix) - 1)).'.'.$suffix;
            if ($i > 20) $candidate = 'user.'.Str::lower(Str::random(8));
        }
        return $candidate;
    }
}
