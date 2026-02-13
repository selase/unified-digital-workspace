<?php

declare(strict_types=1);

it('serves metronic asset files', function (): void {
    $this->get('/metronic-assets/css/styles.css')
        ->assertSuccessful()
        ->assertHeader('cache-control', 'max-age=86400, public');
});

it('blocks metronic asset path traversal attempts', function (): void {
    $this->get('/metronic-assets/%2E%2E/.env')
        ->assertNotFound();
});
