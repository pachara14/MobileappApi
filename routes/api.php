<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SensorController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\relayController;

use App\Http\Controllers\AuthController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/sensor/data', [SensorController::class, 'store']);
Route::post('/resetpassword/direct', [AuthController::class, 'resetPasswordDirect']);
Route::post('/relay/control', [relayController::class, 'updateStatus']);
Route::get('/relay/status', [relayController::class, 'getStatus']);

// ส่วนที่เข้าต้องมีการตรวจสอบ JWT Token
Route::middleware('jwt.verify')->group(function () {
    // Sensor
    // Route::post('/sensor/data', [SensorController::class, 'store']);
    Route::post('/profile/upload', [AuthController::class, 'uploadProfileImage']);
    Route::get('/profile/image', [AuthController::class, 'getProfileImage']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/sensor/data', [SensorController::class, 'index']);
    Route::get('/sensor/latest', [SensorController::class, 'latest']);
    Route::get('/sensor/date', [SensorController::class, 'getByDate']);
    Route::post('/resetpassword/', [AuthController::class, 'changePassword']);
    Route::post('/api/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/api/admin/users/role', [AdminController::class, 'changeUserRole']);
});
