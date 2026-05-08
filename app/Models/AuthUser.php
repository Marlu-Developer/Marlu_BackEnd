<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Jenssegers\Mongodb\Eloquent\Model;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

/**
 * Auth-aware user backed by the existing employees Mongo collection.
 * Login + JWT payload uses Employee_User_Login + Employee_Password_Hash.
 */
class AuthUser extends Model implements AuthenticatableContract, JWTSubject
{
    use Authenticatable;

    protected $connection = 'mongodb';
    protected $collection = 'employees_database_collection';

    protected $hidden = [
        'Employee_Password',
        'Employee_Password_Hash',
        'remember_token',
    ];

    public function getAuthIdentifierName()
    {
        return '_id';
    }

    public function getAuthIdentifier()
    {
        return (string) $this->_id;
    }

    public function getAuthPassword()
    {
        return (string) ($this->Employee_Password_Hash ?? '');
    }

    public function getJWTIdentifier()
    {
        return (string) $this->_id;
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'eid' => $this->Employee_ID,
            'name' => $this->Employee_Full_Name,
            'login' => $this->Employee_User_Login,
            'type' => $this->Employee_User_Type,
            'subType' => $this->Employee_User_SubType,
        ];
    }
}
