<?php

namespace Tests\Feature\Api;

use App\Exceptions\FoundationException;
use App\Models\User;
use App\Modules\Shared\Application\Idempotency\IdempotencyService;
use App\Modules\Shared\Application\Pagination\CursorPaginator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class PaginationAndIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_raw_key_is_hashed_replay_is_stable_and_mismatch_conflicts(): void
    {
        $user = User::factory()->create();
        $service = app(IdempotencyService::class);
        $key = 'synthetic-idempotency-key-0001';
        $record = $service->acquire('platform', null, $user->id, 'test.operation', $key, hash('sha256', 'request-a'));
        $service->complete($record, 201, ['data' => ['id' => 'safe']]);
        $replay = $service->acquire('platform', null, $user->id, 'test.operation', $key, hash('sha256', 'request-a'));

        self::assertSame('completed', $replay->state);
        self::assertSame(hash('sha256', $key), $replay->key_hash);
        self::assertDatabaseMissing('idempotency_records', ['key_hash' => $key]);

        try {
            $service->acquire('platform', null, $user->id, 'test.operation', $key, hash('sha256', 'request-b'));
            self::fail('Mismatched replay was accepted.');
        } catch (FoundationException $exception) {
            self::assertSame('idempotency_conflict', $exception->problemCode);
        }
    }

    public function test_cursor_pages_are_bounded_stable_and_scope_bound(): void
    {
        User::factory()->count(3)->create();
        $paginator = app(CursorPaginator::class);

        $first = $paginator->paginate(User::query(), 'platform:users', [], null, 2);
        self::assertCount(2, $first->items);
        self::assertTrue($first->hasMore);
        self::assertNotNull($first->nextCursor);

        $second = $paginator->paginate(User::query(), 'platform:users', [], $first->nextCursor, 2);
        self::assertCount(1, $second->items);
        self::assertSame([], array_intersect(
            array_map(fn (User $user): string => $user->id, $first->items),
            array_map(fn (User $user): string => $user->id, $second->items),
        ));

        $this->expectException(InvalidArgumentException::class);
        $paginator->paginate(User::query(), 'platform:tenants', [], $first->nextCursor, 2);
    }
}
