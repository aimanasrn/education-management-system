<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case CentreAdmin = 'centre_admin';
    case CampusManager = 'campus_manager';
    case Teacher = 'teacher';
    case HrOfficer = 'hr_officer';
    case FinanceOfficer = 'finance_officer';
    case Student = 'student';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'System administrator',
            self::CentreAdmin => 'Centre administrator',
            self::CampusManager => 'Campus manager',
            self::Teacher => 'Teacher / tutor',
            self::HrOfficer => 'HR officer',
            self::FinanceOfficer => 'Finance officer',
            self::Student => 'Student',
            self::Guardian => 'Parent / guardian',
        };
    }

    /** @return list<string> */
    public function permissions(): array
    {
        $administration = ['campuses.view', 'campuses.manage', 'academics.view', 'academics.manage', 'classes.view', 'classes.manage', 'students.manage', 'users.manage', 'audit.view', 'hr.view', 'finance.view'];

        $learning = match ($this) {
            self::Admin, self::CentreAdmin => ['learning.view', 'admissions.view', 'admissions.manage', 'enrolments.manage', 'links.manage'],
            self::CampusManager => ['learning.view', 'admissions.view', 'admissions.manage', 'enrolments.manage'],
            self::Teacher => ['learning.view'],
            self::Student, self::Guardian => ['learning.view', 'admissions.view', 'admissions.apply'],
            default => [],
        };

        $bookings = ['bookings.view', 'bookings.request'];
        if (in_array($this, [self::Admin, self::CentreAdmin, self::CampusManager], true)) {
            $bookings[] = 'bookings.manage';
        }

        return array_merge(['dashboard.view'], $learning, $bookings, match ($this) {
            self::Admin, self::CentreAdmin => $administration,
            self::CampusManager => ['campuses.view', 'campuses.manage', 'academics.view', 'classes.view', 'classes.manage'],
            self::Teacher => ['campuses.view', 'academics.view', 'classes.view'],
            self::HrOfficer => ['campuses.view', 'hr.view'],
            self::FinanceOfficer => ['campuses.view', 'finance.view'],
            self::Student, self::Guardian => [],
        });
    }
}
