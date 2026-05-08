<?php

namespace App\Http\Resources\Auth;

use Illuminate\Http\Resources\Json\JsonResource;

class AuthUserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            '_id' => (string) ($this->_id ?? ''),
            'id' => $this->Employee_ID ?? null,
            'name' => $this->Employee_Full_Name ?? '',
            'email' => $this->Employee_Email ?? '',
            'phone' => $this->Employee_Phone_Number ?? '',
            'image' => $this->Employee_Photo ?? '',
            'login' => $this->Employee_User_Login ?? '',
            'type' => $this->Employee_User_Type ?? '',
            'subType' => $this->Employee_User_SubType ?? '',
            'role' => method_exists($this->resource, 'getRoleName') ? $this->resource->getRoleName() : null,
            'homePage' => $this->Employee_User_Home_Page ?? null,
        ];
    }
}
