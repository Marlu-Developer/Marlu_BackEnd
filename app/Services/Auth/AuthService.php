<?php

namespace App\Services\Auth;

use App\Models\AuthUser;
use App\Models\EmployeesDatabaseCollection;
use Illuminate\Support\Facades\Hash;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    /**
     * Attempt to log in a user using bcrypt with a transparent migration
     * from legacy MD5 hashes stored in `Employee_Password`.
     *
     * @return array{access_token:string,token_type:string,expires_in:int,refresh_token:string,user:array}|null
     */
    public function login(string $username, string $password): ?array
    {
        $employee = EmployeesDatabaseCollection::where('Employee_User_Login', $username)->first();
        if (!$employee) {
            return null;
        }

        $bcryptHash = (string) ($employee->Employee_Password_Hash ?? '');
        $legacyMd5 = (string) ($employee->Employee_Password ?? '');

        $authenticated = false;
        if ($bcryptHash !== '' && Hash::check($password, $bcryptHash)) {
            $authenticated = true;
        } elseif ($legacyMd5 !== '' && hash_equals($legacyMd5, md5($password))) {
            $employee->Employee_Password_Hash = Hash::make($password);
            $employee->save();
            $authenticated = true;
        }

        if (!$authenticated) {
            return null;
        }

        $user = AuthUser::where('Employee_User_Login', $username)->first();
        if (!$user) {
            return null;
        }

        $token = JWTAuth::fromUser($user);
        return $this->buildTokenPayload($token, $user);
    }

    public function refresh(): array
    {
        $token = JWTAuth::parseToken()->refresh();
        $user = JWTAuth::setToken($token)->authenticate();
        return $this->buildTokenPayload($token, $user);
    }

    public function logout(): void
    {
        JWTAuth::parseToken()->invalidate();
    }

    public function me(): ?array
    {
        $user = JWTAuth::user();
        return $user ? $this->serializeUser($user) : null;
    }

    private function buildTokenPayload(string $token, AuthUser $user): array
    {
        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => (int) (config('jwt.ttl', 60) * 60 * 12),
            'user' => $this->serializeUser($user),
        ];
    }

    private function serializeUser(AuthUser $user): array
    {
        return [
            '_id' => (string) $user->_id,
            'id' => $user->Employee_ID,
            'name' => $user->Employee_Full_Name,
            'email' => $user->Employee_Email,
            'phone' => $user->Employee_Phone_Number,
            'image' => $user->Employee_Photo,
            'login' => $user->Employee_User_Login,
            'type' => $user->Employee_User_Type,
            'subType' => $user->Employee_User_SubType,
            'homePage' => $user->Employee_User_Home_Page,
        ];
    }
}
