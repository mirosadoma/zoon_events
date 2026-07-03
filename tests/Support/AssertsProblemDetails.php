<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;

trait AssertsProblemDetails
{
    protected function assertProblemDetails(TestResponse $response, int $status, string $code): void
    {
        $response->assertStatus($status)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', $status)
            ->assertJsonPath('code', $code)
            ->assertJsonStructure([
                'type',
                'title',
                'status',
                'code',
                'detail',
                'instance',
                'correlation_id',
            ]);
    }
}
