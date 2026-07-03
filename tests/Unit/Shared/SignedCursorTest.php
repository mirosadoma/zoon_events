<?php

namespace Tests\Unit\Shared;

use App\Modules\Shared\Application\Pagination\CursorPage;
use App\Modules\Shared\Application\Pagination\SignedCursor;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SignedCursorTest extends TestCase
{
    #[Test]
    public function signed_cursor_round_trips_for_the_same_scope_and_filters(): void
    {
        CarbonImmutable::setTestNow('2026-07-02T20:15:00Z');

        $signed = SignedCursor::issue(
            createdAt: '2026-07-02T18:00:00Z',
            id: '01JZ4T3YP1AZZZZZZZZZZZZZZZ',
            scope: 'tenant:01JZ4TENANT00000000000000',
            filters: ['status' => 'active'],
            expiresInMinutes: 10,
        );

        $decoded = SignedCursor::decode(
            $signed,
            scope: 'tenant:01JZ4TENANT00000000000000',
            filters: ['status' => 'active'],
        );

        $this->assertSame('01JZ4T3YP1AZZZZZZZZZZZZZZZ', $decoded->id);
        $this->assertSame('2026-07-02T18:00:00Z', $decoded->createdAt);

        CarbonImmutable::setTestNow();
    }

    #[Test]
    public function tampered_cursor_is_rejected(): void
    {
        $signed = SignedCursor::issue(
            createdAt: '2026-07-02T18:00:00Z',
            id: '01JZ4T3YP1AZZZZZZZZZZZZZZZ',
            scope: 'tenant:01JZ4TENANT00000000000000',
            filters: ['status' => 'active'],
            expiresInMinutes: 10,
        );

        $this->expectException(InvalidArgumentException::class);

        SignedCursor::decode(substr($signed, 0, -3).'abc', 'tenant:01JZ4TENANT00000000000000', ['status' => 'active']);
    }

    #[Test]
    public function expired_cursor_is_rejected(): void
    {
        CarbonImmutable::setTestNow('2026-07-02T20:15:00Z');

        $signed = SignedCursor::issue(
            createdAt: '2026-07-02T18:00:00Z',
            id: '01JZ4T3YP1AZZZZZZZZZZZZZZZ',
            scope: 'tenant:01JZ4TENANT00000000000000',
            filters: ['status' => 'active'],
            expiresInMinutes: 1,
        );

        CarbonImmutable::setTestNow('2026-07-02T20:17:00Z');

        $this->expectException(InvalidArgumentException::class);

        SignedCursor::decode($signed, 'tenant:01JZ4TENANT00000000000000', ['status' => 'active']);
    }

    #[Test]
    public function cross_scope_cursor_is_rejected(): void
    {
        $signed = SignedCursor::issue(
            createdAt: '2026-07-02T18:00:00Z',
            id: '01JZ4T3YP1AZZZZZZZZZZZZZZZ',
            scope: 'tenant:01JZ4TENANT00000000000000',
            filters: ['status' => 'active'],
            expiresInMinutes: 10,
        );

        $this->expectException(InvalidArgumentException::class);

        SignedCursor::decode($signed, 'tenant:ANOTHER', ['status' => 'active']);
    }

    #[Test]
    public function cursor_page_exposes_expected_metadata(): void
    {
        $page = new CursorPage(
            items: [['id' => 1]],
            nextCursor: 'opaque-cursor',
            hasMore: true,
            pageSize: 50,
        );

        $this->assertSame('opaque-cursor', $page->nextCursor);
        $this->assertTrue($page->hasMore);
    }
}
