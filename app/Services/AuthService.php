<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthService
{
    public function register(array $data): User
    {
        if (isset($data['name']) && empty($data['full_name'])) {
            $data['full_name'] = $data['name'];
        }

        unset($data['name']);

        $data['role_id'] = 3;
        $data['password'] = Hash::make($data['password']);

        return User::create($data)->refresh();
    }

    public function login(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (!$user || !$this->passwordMatches($password, (string) $user->password)) {
            return null;
        }

        if (password_get_info((string) $user->password)['algoName'] !== 'bcrypt') {
            $user->forceFill([
                'password' => Hash::make($password),
            ])->save();

            $user->refresh();
        }

        return $user;
    }

    private function passwordMatches(string $password, string $storedPassword): bool
    {
        if ($storedPassword === '') {
            return false;
        }

        try {
            if (Hash::check($password, $storedPassword)) {
                return true;
            }
        } catch (\RuntimeException) {
            // Old seed data may contain plain text, md5, or sha1 passwords.
        }

        return hash_equals($storedPassword, $password)
            || hash_equals(strtolower($storedPassword), md5($password))
            || hash_equals(strtolower($storedPassword), sha1($password));
    }
}
