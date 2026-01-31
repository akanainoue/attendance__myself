<?php

use Illuminate\Support\Facades\Route;

use \App\Http\Controllers\AdminAuthController;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use \App\Http\Controllers\PunchController;
use \App\Http\Controllers\StaffAttendanceController;
use \App\Http\Controllers\StaffRequestController;
use \App\Http\Controllers\AdminAttendanceController;
use \App\Http\Controllers\AdminRequestController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth','verified'])->group(function () {
     // 打刻（出勤画面）
    Route::get('/attendance', [PunchController::class, 'show']);
    Route::post('/attendance/clock-in',  [PunchController::class, 'clockIn']);
    Route::post('/attendance/clock-out', [PunchController::class, 'clockOut']);
    Route::post('/attendance/break-start', [PunchController::class, 'breakStart']);
    Route::post('/attendance/break-end',   [PunchController::class, 'breakEnd']);

    // 勤怠一覧・詳細・備考・申請
    Route::get('/attendance/list',               [StaffAttendanceController::class, 'index']);        // 勤怠一覧
    Route::get('/attendance/detail/date/{ymd}', [StaffRequestController::class, 'showByDate']);  //勤怠詳細（空）
    Route::get('/attendance/detail/{id}',        [StaffAttendanceController::class, 'detail']);         // 勤怠詳細
    Route::post('/attendance/detail/{id}/notes', [StaffRequestController::class, 'upsert']);      //備考

    // Route::post('/attendance/detail/date/{ymd}', [StaffRequestController::class, 'upsertByDate']);   //勤怠詳細（空）
    Route::get('/stamp_correction_request/list', [StaffAttendanceController::class, 'requestIndex']); // 申請一覧
});


Route::prefix('admin')->group(function () {
    Route::get('/login', [AdminAuthController::class, 'create']);
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);

    // 管理者の保護エリア（例）
    Route::middleware('auth:admin')->group(function () {
        Route::get('attendance/list',                      [AdminAttendanceController::class, 'index']);  //勤怠一覧
        Route::get('attendance/{id}',                      [AdminAttendanceController::class, 'detail']); //勤怠詳細
        // Route::get('attendance/date/{userId}/{ymd}', [StaffRequestController::class, 'showByDate']);  //勤怠詳細（空）
        //ユーザーが申請したもののみ開ける
        Route::patch('attendance/{id}',                   [AdminAttendanceController::class, 'update']); //勤怠更新
        Route::get('staff/list',                        [AdminAttendanceController::class, 'staffIndex']); //スタッフ一覧
        Route::get('attendance/staff/{id}',            [AdminAttendanceController::class, 'indexByStaff']);  //スタッフ別勤怠一覧

        Route::get('stamp_correction_request/list',       [AdminRequestController::class, 'requestIndex']); //申請一覧
        Route::get('stamp_correction_request/approve/{attendance_correct_request}', [AdminRequestController::class, 'showRequest']);  //申請詳細画面
        Route::patch('requests/{id}/accept',                    [AdminRequestController::class, 'accept']);
    });
});
