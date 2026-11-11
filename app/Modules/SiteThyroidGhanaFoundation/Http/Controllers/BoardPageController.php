<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use App\Modules\SiteThyroidGhanaFoundation\Support\TeamRoster;
use Illuminate\Contracts\View\View;

final class BoardPageController extends PublicPageController
{
    public function show(): View
    {
        return view('site-thyroid-ghana-foundation::public.pages.board', ['theme' => $this->theme, 'board' => TeamRoster::board()]);
    }
}
