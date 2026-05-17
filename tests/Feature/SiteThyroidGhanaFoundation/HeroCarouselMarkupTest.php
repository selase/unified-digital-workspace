<?php

it('keeps hero slide visibility independent from display toggling', function () {
    $blade = file_get_contents(base_path('app/Modules/SiteThyroidGhanaFoundation/Views/public/home.blade.php'));

    expect($blade)
        ->not->toContain('x-show="current === {{ $i }}"')
        ->not->toContain('x-cloak')
        ->toContain(':class="current === {{ $i }} ? \'z-10 opacity-100\' : \'z-0 opacity-0 pointer-events-none\'"');
});
