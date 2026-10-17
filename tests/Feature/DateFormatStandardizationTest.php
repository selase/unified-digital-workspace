<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Blade;

it('configures global institutional date and datetime formats as d/m/Y', function (): void {
    expect(config('app.date_format'))->toBe('d/m/Y')
        ->and(config('app.datetime_format'))->toBe('d/m/Y H:i');
});

it('formats Carbon and CarbonImmutable instances using institutional macros', function (): void {
    $mutable = Carbon::create(2026, 10, 16, 14, 35, 0, 'UTC');
    $immutable = CarbonImmutable::create(2026, 10, 16, 14, 35, 0, 'UTC');

    expect($mutable->toInstitutionalDate())->toBe('16/10/2026')
        ->and($mutable->toInstitutionalDateTime())->toBe('16/10/2026 14:35')
        ->and($immutable->toInstitutionalDate())->toBe('16/10/2026')
        ->and($immutable->toInstitutionalDateTime())->toBe('16/10/2026 14:35');
});

it('renders @date and @datetime Blade directives in d/m/Y format and handles null safely', function (): void {
    $date = Carbon::create(2026, 3, 5, 9, 15, 0, 'UTC');
    $nullDate = null;

    $renderedDate = Blade::render('@date($date)', ['date' => $date]);
    $renderedDateTime = Blade::render('@datetime($date)', ['date' => $date]);
    $renderedNull = Blade::render('@date($nullDate)', ['nullDate' => $nullDate]);

    expect(mb_trim($renderedDate))->toBe('05/03/2026')
        ->and(mb_trim($renderedDateTime))->toBe('05/03/2026 09:15')
        ->and(mb_trim($renderedNull))->toBe('—');
});
