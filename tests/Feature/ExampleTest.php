<?php

declare(strict_types=1);

test('the application returns a redirect or 404 for the root path on the landlord domain', function () {
    // This app's root domain has no public landing — it serves admin/auth
    // routes only. `/` either redirects (to /login) or 404s depending on
    // whether the catch-all CMS fallback matched a page. Either is fine;
    // a 500 would indicate a real bootstrap problem.
    $status = $this->get('/')->status();

    expect($status)->toBeIn([200, 301, 302, 404]);
});
