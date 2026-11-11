<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use Illuminate\Contracts\View\View;

final class PartnerPageController extends PublicPageController
{
    public function show(): View
    {
        return view('site-thyroid-ghana-foundation::public.pages.partner', ['theme' => $this->theme]);
    }
}
