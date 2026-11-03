<?php

use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampusBookingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FoundationController;
use App\Http\Controllers\Api\JwtAuthController;
use App\Http\Controllers\Api\LearningController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\UserAccessController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json(['message' => 'Laravel API is running']);
});

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/jwt/login', [JwtAuthController::class, 'login'])->middleware('throttle:login');
Route::post('/jwt/refresh', [JwtAuthController::class, 'refresh'])->middleware(['auth:jwt', 'active', 'cache.headers:no_store', 'throttle:jwt-refresh']);

Route::middleware(['auth:sanctum,jwt', 'active', 'cache.headers:no_store'])->group(function (): void {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', DashboardController::class);
    Route::prefix('bookings')->group(function (): void {
        Route::get('/options', [CampusBookingController::class, 'options']);
        Route::get('/', [CampusBookingController::class, 'index']);
        Route::post('/', [CampusBookingController::class, 'store']);
        Route::get('/{id}', [CampusBookingController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [CampusBookingController::class, 'update'])->whereNumber('id');
        Route::post('/{id}/transition', [CampusBookingController::class, 'transition'])->whereNumber('id');
    });
    Route::prefix('admissions')->group(function (): void {
        Route::get('/options', [AdmissionController::class, 'options']);
        Route::get('/', [AdmissionController::class, 'index']);
        Route::post('/', [AdmissionController::class, 'store']);
        Route::get('/{id}', [AdmissionController::class, 'show'])->whereNumber('id');
        Route::put('/{id}', [AdmissionController::class, 'update'])->whereNumber('id');
        Route::post('/{id}/transition', [AdmissionController::class, 'transition'])->whereNumber('id');
        Route::post('/{id}/documents', [AdmissionController::class, 'upload'])->whereNumber('id');
        Route::get('/{id}/documents/{documentId}', [AdmissionController::class, 'download'])->whereNumber(['id', 'documentId']);
        Route::post('/{id}/documents/{documentId}/verify', [AdmissionController::class, 'verify'])->whereNumber(['id', 'documentId']);
    });
    Route::prefix('learning')->group(function (): void {
        Route::get('/students', [LearningController::class, 'students']);
        Route::get('/students/{id}', [LearningController::class, 'student'])->whereNumber('id');
        Route::get('/options', [LearningController::class, 'options']);
        Route::get('/enrolments', [LearningController::class, 'enrolments']);
        Route::post('/enrolments', [LearningController::class, 'enrol']);
        Route::post('/enrolments/{id}/transition', [LearningController::class, 'changeEnrolment'])->whereNumber('id');
        Route::post('/links', [LearningController::class, 'link']);
    });
    Route::get('/admin/dashboard', AdminDashboardController::class)->middleware('role:admin,centre_admin');
    Route::get('/system/users/options', [UserAccessController::class, 'options']);
    Route::get('/system/users', [UserAccessController::class, 'index']);
    Route::post('/system/users', [UserAccessController::class, 'store']);
    Route::put('/system/users/{id}', [UserAccessController::class, 'update'])->where('id', '[1-9][0-9]{0,17}');
    Route::get('/system/audit', AuditLogController::class);
    Route::prefix('foundation/{resource}')->group(function (): void {
        Route::get('/options', [FoundationController::class, 'options']);
        Route::get('/', [FoundationController::class, 'index']);
        Route::post('/', [FoundationController::class, 'store']);
        Route::get('/{id}', [FoundationController::class, 'show'])->where('id', '[1-9][0-9]{0,17}');
        Route::put('/{id}', [FoundationController::class, 'update'])->where('id', '[1-9][0-9]{0,17}');
    });
    Route::middleware('role:admin,centre_admin')->prefix('students')->name('students.')->group(function (): void {
        Route::get('/', [StudentController::class, 'index'])->name('index');
        Route::post('/', [StudentController::class, 'store'])->name('store');
        Route::get('/{student}', [StudentController::class, 'show'])->whereNumber('student')->withTrashed()->name('show');
        Route::put('/{student}', [StudentController::class, 'update'])->whereNumber('student')->withTrashed()->name('update');
        Route::post('/{student}/archive', [StudentController::class, 'archive'])->whereNumber('student')->withTrashed()->name('archive');
        Route::post('/{student}/restore', [StudentController::class, 'restore'])->whereNumber('student')->withTrashed()->name('restore');
    });
});
