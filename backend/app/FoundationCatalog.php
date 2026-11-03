<?php

namespace App;

use App\Models\AcademicTerm;
use App\Models\AcademicYear;
use App\Models\Building;
use App\Models\Campus;
use App\Models\CampusClosure;
use App\Models\Programme;
use App\Models\Room;
use App\Models\SchoolClass;
use App\Models\Subject;

class FoundationCatalog
{
    /** @return array<string, array{model: class-string, permission: string, fields: list<string>, relations: list<string>}> */
    public static function all(): array
    {
        return [
            'campuses' => ['model' => Campus::class, 'permission' => 'campuses', 'fields' => ['code', 'name', 'email', 'phone', 'address', 'operating_hours', 'status'], 'relations' => []],
            'buildings' => ['model' => Building::class, 'permission' => 'campuses', 'fields' => ['campus_id', 'code', 'name', 'status'], 'relations' => ['campus']],
            'rooms' => ['model' => Room::class, 'permission' => 'campuses', 'fields' => ['campus_id', 'building_id', 'code', 'name', 'capacity', 'facilities', 'status'], 'relations' => ['campus', 'building']],
            'years' => ['model' => AcademicYear::class, 'permission' => 'academics', 'fields' => ['code', 'name', 'starts_on', 'ends_on', 'status'], 'relations' => []],
            'terms' => ['model' => AcademicTerm::class, 'permission' => 'academics', 'fields' => ['academic_year_id', 'code', 'name', 'starts_on', 'ends_on', 'status'], 'relations' => ['academicYear']],
            'programmes' => ['model' => Programme::class, 'permission' => 'academics', 'fields' => ['code', 'name', 'description', 'status'], 'relations' => []],
            'subjects' => ['model' => Subject::class, 'permission' => 'academics', 'fields' => ['programme_id', 'code', 'name', 'status'], 'relations' => ['programme']],
            'classes' => ['model' => SchoolClass::class, 'permission' => 'classes', 'fields' => ['campus_id', 'academic_year_id', 'academic_term_id', 'programme_id', 'subject_id', 'room_id', 'code', 'name', 'capacity', 'status', 'teacher_ids'], 'relations' => ['campus', 'academicYear', 'academicTerm', 'programme', 'subject', 'room', 'teachers']],
            'closures' => ['model' => CampusClosure::class, 'permission' => 'campuses', 'fields' => ['campus_id', 'name', 'kind', 'starts_on', 'ends_on', 'status'], 'relations' => ['campus']],
        ];
    }

    /** @return array{model: class-string, permission: string, fields: list<string>, relations: list<string>} */
    public static function get(string $resource): array
    {
        abort_unless(isset(self::all()[$resource]), 404);

        return self::all()[$resource];
    }

    public static function reference(string $field): ?string
    {
        return [
            'campus_id' => 'campuses', 'building_id' => 'buildings', 'academic_year_id' => 'years',
            'academic_term_id' => 'terms', 'programme_id' => 'programmes', 'subject_id' => 'subjects',
            'room_id' => 'rooms',
        ][$field] ?? null;
    }
}
