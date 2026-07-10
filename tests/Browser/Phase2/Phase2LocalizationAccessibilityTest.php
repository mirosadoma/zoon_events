<?php

namespace Tests\Browser\Phase2;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('browser')]
#[Group('phase-2')]
final class Phase2LocalizationAccessibilityTest extends TestCase
{
    public function test_wallet_and_check_in_frontend_browser_suite_passes(): void
    {
        $process = Process::fromShellCommandline('npm run test -- phase2-browser', base_path());
        $process->setTimeout(120);
        $process->run();

        self::assertTrue(
            $process->isSuccessful(),
            trim($process->getErrorOutput()."\n".$process->getOutput()),
        );
    }
}
