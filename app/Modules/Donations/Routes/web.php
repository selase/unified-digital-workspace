<?php

declare(strict_types=1);

use App\Modules\Donations\Http\Controllers\CampaignController;
use App\Modules\Donations\Http\Controllers\DonationController;
use App\Modules\Donations\Http\Controllers\DonationDashboardController;
use App\Modules\Donations\Http\Controllers\DonorController;
use App\Modules\Donations\Http\Controllers\PledgeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DonationDashboardController::class, 'index'])->name('index');

Route::get('/donors', [DonorController::class, 'index'])->name('donors.index');
Route::post('/donors', [DonorController::class, 'store'])->name('donors.store');
Route::get('/donors/{donor}', [DonorController::class, 'show'])->name('donors.show');
Route::put('/donors/{donor}', [DonorController::class, 'update'])->name('donors.update');

Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
Route::post('/campaigns/{campaign}/toggle', [CampaignController::class, 'toggle'])->name('campaigns.toggle');

Route::get('/gifts', [DonationController::class, 'index'])->name('donations.index');
Route::post('/gifts', [DonationController::class, 'store'])->name('donations.store');
Route::get('/gifts/{donation}/receipt', [DonationController::class, 'receipt'])->name('donations.receipt');
Route::post('/gifts/{donation}/email', [DonationController::class, 'email'])->name('donations.email');
Route::post('/gifts/{donation}/void', [DonationController::class, 'void'])->name('donations.void');

Route::get('/pledges', [PledgeController::class, 'index'])->name('pledges.index');
Route::post('/pledges', [PledgeController::class, 'store'])->name('pledges.store');
Route::post('/pledges/{pledge}/receive', [PledgeController::class, 'receive'])->name('pledges.receive');
Route::post('/pledges/{pledge}/link', [PledgeController::class, 'link'])->name('pledges.link');
Route::post('/pledges/{pledge}/status', [PledgeController::class, 'status'])->name('pledges.status');
