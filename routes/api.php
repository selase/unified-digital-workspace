<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());

Route::group(['prefix' => 'llm', 'middleware' => ['App\Http\Middleware\AuthenticateWithApiKey', 'App\Http\Middleware\EnsureTenantHasLlmTokens']], function () {
    Route::get('/test-quota', function () {
        return response()->json(['message' => 'Quota OK']);
    });

    Route::get('/test-throttle', function () {
        return response()->json(['message' => 'Throttle OK']);
    })->middleware('App\Http\Middleware\ThrottleLlmRequests:60,1');

    Route::post('/chat', function () {
        // Placeholder for real LLM chat
        return response()->json(['message' => 'Chat response']);
    });
});
