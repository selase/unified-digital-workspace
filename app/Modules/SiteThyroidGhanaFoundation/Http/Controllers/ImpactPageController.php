<?php

declare(strict_types=1);

namespace App\Modules\SiteThyroidGhanaFoundation\Http\Controllers;

use App\Modules\SiteThyroidGhanaFoundation\Support\ImpactFigures;
use Illuminate\Contracts\View\View;

final class ImpactPageController extends PublicPageController
{
    public function show(): View
    {
        return view('site-thyroid-ghana-foundation::public.pages.impact', ['theme' => $this->theme, 'figures' => ImpactFigures::headline(), 'pillars' => ImpactFigures::pillars()]);
    }
}
