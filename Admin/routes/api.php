<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\MobileApiController;
use App\Http\Controllers\PetugasApiController;
use App\Http\Controllers\PetugasAuthController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureApiRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/petugas/login', [PetugasAuthController::class, 'login']);
});

Route::get('/informasi', [MobileApiController::class, 'informations']);

Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Chat laporan: dipakai Pengguna dan Petugas (hak akses dicek di ChatService).
    Route::get('/chat/laporan/{reportId}', [ChatController::class, 'context'])->whereNumber('reportId');
    Route::post('/chat/laporan/{reportId}/sesi', [ChatController::class, 'session'])->whereNumber('reportId');
    Route::get('/chat/laporan/{reportId}/pesan', [ChatController::class, 'messages'])->whereNumber('reportId');
    Route::post('/chat/laporan/{reportId}/pesan', [ChatController::class, 'send'])->whereNumber('reportId');

    Route::middleware(EnsureApiRole::class.':pengguna')->group(function () {
        Route::get('/user', fn (Request $request) => $request->user()->makeHidden('no_id_card'));

        Route::get('/laporan', [MobileApiController::class, 'reports']);
        Route::get('/riwayat', [MobileApiController::class, 'reports']);
        Route::get('/laporan-terakhir', [MobileApiController::class, 'latestReport']);
        Route::post('/lapor', [MobileApiController::class, 'storeReport']);
        Route::put('/laporan/{id}', [MobileApiController::class, 'updateReport'])->whereNumber('id');
        Route::delete('/laporan/{id}', [MobileApiController::class, 'deleteReport'])->whereNumber('id');

        Route::get('/notifikasi/{userId}', [MobileApiController::class, 'notifications'])->whereNumber('userId');
        Route::delete('/notifikasi/{userId}/{reportId}', [MobileApiController::class, 'dismissNotification'])
            ->whereNumber('userId')
            ->whereNumber('reportId');

        Route::put('/pengguna/{userId}/profil', [MobileApiController::class, 'updateProfile'])->whereNumber('userId');
        Route::put('/pengguna/{userId}/password', [MobileApiController::class, 'updatePassword'])->whereNumber('userId');
    });

    Route::prefix('petugas')
        ->middleware(EnsureApiRole::class.':petugas')
        ->group(function () {
            Route::get('/dashboard', [PetugasApiController::class, 'dashboard']);
            Route::get('/laporan', [PetugasApiController::class, 'reports']);
            Route::get('/laporan/{id}', [PetugasApiController::class, 'showReport'])->whereNumber('id');
            Route::get('/laporan/{id}/bukti/{index}', [PetugasApiController::class, 'evidence'])
                ->whereNumber('id')
                ->whereNumber('index');
            Route::patch('/laporan/{id}/penanganan', [PetugasApiController::class, 'updateHandling'])
                ->whereNumber('id');
        });
});
