<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RtpmController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\HomeController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['prefix' => 'verify'], function () {
    Route::post('streamKey', [ApiController::class, 'verify_streamKey'])->name('verify.streamKey');
    Route::post('streamDone', [ApiController::class, 'verify_streamDone'])->name('verify.streamDone');
});

Route::group(['prefix' => 'check'], function () {
    Route::post('/copyright', [ApiController::class, 'copyright'])->name('live_stream.copyright');
});

Route::group(['prefix' => 'share'], function () {
    Route::get('/{type}/{id}', [ApiController::class, 'share_stream'])->name('stream.share');
});
