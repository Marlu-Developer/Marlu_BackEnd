<?php

namespace App\Services\Employees;

use App\Models\EmployeeSchedulesDatabaseCollection;
use App\Repositories\Employees\EmployeesRepository;
use App\Repositories\Employees\PermitsRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

class EmployeesService
{
    public function __construct(
        private EmployeesRepository $employees,
        private PermitsRepository $permits,
    ) {
    }

    public function list(?string $search = null): array
    {
        return [
            'activeListData' => $this->employees->listByStatus('active', $search),
            'inactiveListData' => $this->employees->listByStatus('inactive', $search),
        ];
    }

    public function permitsDashboard(): array
    {
        return [
            'userPermits' => $this->permits->all(),
            'employees' => $this->employees->activeOrdered(),
        ];
    }

    public function updateEmployeePermission(int $employeeId, string $key, mixed $value): bool
    {
        if ($key === 'Employee_User_SSS_Visible' && is_array($value)) {
            $value = implode(',', array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== '')));
        }
        return $this->employees->updateField($employeeId, $key, $value);
    }

    public function updatePermitPermission(string $userType, string $userSubType, string $key, mixed $value): bool
    {
        if ($key === 'Permit_SSS_Visible' && is_array($value)) {
            $value = implode(',', array_values(array_filter(array_map('strval', $value), fn ($v) => $v !== '')));
        }
        return $this->permits->updateField($userType, $userSubType, $key, $value);
    }

    public function resetEmployeeToTypeDefault(int $employeeId, array $mappings): array
    {
        $employee = $this->employees->findByEmployeeId($employeeId);
        if (!$employee) {
            throw new \DomainException('Employee not found', 404);
        }

        $permit = $this->permits->findByType(
            (string) $employee->Employee_User_Type,
            (string) $employee->Employee_User_SubType,
        );
        if (!$permit) {
            throw new \DomainException('Permit template not found', 404);
        }

        $patch = [];
        foreach ($mappings as $map) {
            $employeeKey = (string) ($map['employeeKey'] ?? '');
            $permitKey = (string) ($map['permitKey'] ?? '');
            if ($employeeKey === '' || $permitKey === '') {
                continue;
            }

            $val = $permit->getAttribute($permitKey);
            if ($val === null) {
                $val = str_ends_with($employeeKey, '_Days')
                    ? null
                    : ($employeeKey === 'Employee_User_Home_Page' ? 'Sales Dashboard - Data' : false);
            }

            $patch[$employeeKey] = $val;
        }

        if ($patch !== []) {
            $this->employees->updateMany($employeeId, $patch);
        }

        return $patch;
    }

    public function createEmployee(array $data): void
    {
        $employeeId = (int) ($data['id'] ?? 0);
        if ($employeeId <= 0) {
            throw new \DomainException('Missing employee id', 422);
        }
        if ($this->employees->existsByEmployeeId($employeeId)) {
            throw new \DomainException('Employee ID already in use', 409);
        }

        $picFilename = $this->saveProfilePic((string) ($data['pic'] ?? ''));
        $rawPassword = (string) ($data['password'] ?? '');

        $this->employees->insertOne([
            'Employee_ID' => $employeeId,
            'Employee_Photo' => $picFilename,
            'Employee_Gender' => $data['gender'] ?? '',
            'Employee_Full_Name' => $data['alias'] ?? '',
            'Employee_Alias' => $data['full_name'] ?? '',
            'Employee_Phone_Number' => $data['phone'] ?? '',
            'Employee_Email' => $data['email'] ?? '',
            'Employee_CalendarID' => $data['calendarId'] ?? '',
            'Employee_Birthdate' => $data['birthdate'] ?? '',
            'Employee_Address' => $data['address'] ?? '',
            'Employee_Country' => $data['country'] ?? '',
            'Employee_Province' => $data['province'] ?? '',
            'Employee_City' => $data['city'] ?? '',
            'Employee_PostalCode' => $data['postalCode'] ?? '',
            'Employee_Driving_license' => $data['driving_license'] ?? '',
            'Employee_English' => $data['english'] ?? '',
            'Employee_Marble_Floors' => $data['marble_floors'] ?? '',
            'Employee_Marble_Tables' => $data['marble_tables'] ?? '',
            'Employee_Showers' => $data['showers'] ?? '',
            'Employee_Grout_Floors' => $data['grout_floors'] ?? '',
            'Empolyee_Comments_Section' => $data['comments'] ?? '',
            'Employee_Call_Agent_ID' => $data['agent_id'] ?? '',
            'Employee_Call_Agent_Phone' => $data['agent_phone'] ?? '',
            'Employee_User_Login' => $data['user_login'] ?? '',
            'Employee_User_Type' => $data['user_type'] ?? '',
            'Employee_User_SubType' => $data['user_subtype'] ?? '',
            'Employee_User_Status' => $data['user_status'] ?? 'active',
            'Employee_Password_Hash' => $rawPassword !== '' ? Hash::make($rawPassword) : '',
            'Employee_Position' => $data['position'] ?? '',
            'Employee_Start_Date' => $data['starting_date'] ?? '',
            'Employee_Ending_Date' => $data['ending_date'] ?? '',
            'Empolyee_Wage_Type' => $data['wage_type'] ?? '',
            'Empolyee_Hourly_Wage' => $data['per_hour'] ?? '',
            'Empolyee_Monthly_Wage' => $data['per_month'] ?? '',
            'Empolyee_Vacation_Days' => $data['annual_vacation_days'] ?? '',
            'Empolyee_Vacation_Days_Used' => $data['used_vacation_days'] ?? '',
            'Empolyee_Documents' => $data['files-names'] ?? '',
            'Empolyee_Tags' => $data['tags'] ?? '',
            'Empolyee_Factor_Job' => $data['factor_per_job'] ?? '',
        ]);

        $schedule = $data['schedule'] ?? [];
        if (is_array($schedule) && !empty($schedule)) {
            EmployeeSchedulesDatabaseCollection::updateOrCreate(
                ['Employee_ID' => $employeeId],
                array_merge(['Employee_ID' => $employeeId], $this->normalizeSchedule($schedule)),
            );
        }
    }

    public function uploadPdf(string $name, string $base64Content): array
    {
        $dir = public_path('upload/pdf');
        if (!File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true);
        }
        $safeName = basename($name);
        $path = $dir . DIRECTORY_SEPARATOR . $safeName;
        File::put($path, base64_decode($base64Content, true) ?: '');
        return ['ok' => true, 'name' => $safeName];
    }

    private function saveProfilePic(string $pic): string
    {
        if ($pic === '' || !str_contains($pic, 'base64')) {
            return '';
        }
        $parts = explode(',', $pic, 2);
        if (count($parts) !== 2) {
            return '';
        }
        $binary = base64_decode($parts[1], true);
        if ($binary === false) {
            return '';
        }
        $imgDir = public_path('upload/img');
        if (!File::isDirectory($imgDir)) {
            File::makeDirectory($imgDir, 0755, true);
        }
        $name = time() . '.png';
        File::put($imgDir . DIRECTORY_SEPARATOR . $name, $binary);
        return $name;
    }

    private function normalizeSchedule(array $schedule): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $out = [];
        foreach ($days as $day) {
            $out["{$day}_enabled"] = !empty($schedule["{$day}_enabled"]);
            $out["{$day}_start"] = $schedule["{$day}_start"] ?? null;
            $out["{$day}_end"] = $schedule["{$day}_end"] ?? null;
        }
        return $out;
    }
}
