<?php

namespace Tests\Support;

trait UsesFakeWalletAdapters
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'wallet.default_apple_adapter' => 'fake',
            'wallet.default_google_adapter' => 'fake',
        ]);
    }
}
