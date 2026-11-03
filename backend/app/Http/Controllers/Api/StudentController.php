<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListStudentsRequest;
use App\Http\Requests\SaveStudentRequest;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use App\Models\StudentEnrolment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class StudentController extends Controller
{
    public function index(ListStudentsRequest $request): AnonymousResourceCollection
    {
        $query = Student::query();
        $status = $request->validated('status', 'active');
        if ($status === 'archived') {
            $query->onlyTrashed();
        } elseif ($status === 'all') {
            $query->withTrashed();
        }

        $search = trim($request->validated('q') ?? '');
        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(function (Builder $query) use ($pattern): void {
                foreach (['student_number', 'full_name', 'email'] as $column) {
                    $query->orWhereRaw("LOWER({$column}) LIKE ? ESCAPE '!'", [$pattern]);
                }
            });
        }

        return StudentResource::collection(
            $query->orderByDesc('id')->paginate($request->integer('per_page', 15))->withQueryString()
        );
    }

    public function store(SaveStudentRequest $request): JsonResponse
    {
        $student = Student::create($request->validated());

        return (new StudentResource($student))->response()->setStatusCode(201);
    }

    public function show(Student $student): StudentResource
    {
        return new StudentResource($student);
    }

    public function update(SaveStudentRequest $request, Student $student): StudentResource
    {
        abort_if($student->trashed(), 409, 'Restore this student before editing their record.');
        $student->update($request->validated());

        return new StudentResource($student);
    }

    public function archive(Student $student): StudentResource
    {
        DB::transaction(function () use ($student): void {
            DB::table('access_locks')->where('name', 'access-control')->lockForUpdate()->first();
            $student->refresh();
            abort_if(StudentEnrolment::where('student_id', $student->id)->whereDate('ends_on', '>=', now('Asia/Kuala_Lumpur')->toDateString())->exists(), 409, 'End current and future enrolments before archiving this student.');
            if (! $student->trashed()) {
                $student->delete();
            }
        });

        return new StudentResource($student);
    }

    public function restore(Student $student): StudentResource
    {
        if ($student->trashed()) {
            $student->restore();
        }

        return new StudentResource($student);
    }
}
