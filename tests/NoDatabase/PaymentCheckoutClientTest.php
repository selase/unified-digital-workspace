<?php

declare(strict_types=1);

use Symfony\Component\Process\Process;

it('keeps payment confirmation recoverable across early failures and delayed checkout', function (): void {
    $process = new Process(['node', '--test', __DIR__.'/payment-checkout.test.mjs']);
    $process->setTimeout(30);
    $process->mustRun();

    expect($process->getExitCode())->toBe(0);
});
