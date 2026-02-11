<?php

declare(strict_types=1);

use App\Modules\Memos\Http\Controllers\Api\V1\MemoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'module' => 'memos',
        'version' => config('modules.memos.version', '1.0.0'),
        'status' => 'active',
    ]);
})->name('index');

Route::prefix('v1')->name('v1.')->group(function (): void {
    Route::get('memos', [MemoController::class, 'index'])->name('memos.index');
    Route::post('memos', [MemoController::class, 'store'])->name('memos.store');
    Route::get('memos/{memo}', [MemoController::class, 'show'])->name('memos.show');
    Route::put('memos/{memo}', [MemoController::class, 'update'])->name('memos.update');
    Route::delete('memos/{memo}', [MemoController::class, 'destroy'])->name('memos.destroy');

    Route::post('memos/{memo}/signature', [MemoController::class, 'storeSignature'])->name('memos.signature');
    Route::post('memos/{memo}/send-code', [MemoController::class, 'sendVerificationCode'])->name('memos.send-code');
    Route::post('memos/{memo}/confirm-send', [MemoController::class, 'confirmSend'])->name('memos.confirm-send');
    Route::post('memos/{memo}/acknowledge', [MemoController::class, 'acknowledge'])->name('memos.acknowledge');
    Route::post('memos/{memo}/minutes', [MemoController::class, 'storeMinute'])->name('memos.minutes.store');
    Route::post('memos/{memo}/share', [MemoController::class, 'share'])->name('memos.share');
    Route::post('memos/{memo}/actions', [MemoController::class, 'storeAction'])->name('memos.actions.store');
    Route::patch('memos/{memo}/actions/{action}', [MemoController::class, 'updateAction'])->name('memos.actions.update');
});
