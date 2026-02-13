<?php

declare(strict_types=1);

use App\Modules\Forums\Http\Controllers\Web\ForumHubController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/forums/hub');

Route::get('/hub', [ForumHubController::class, 'index'])->name('hub');
