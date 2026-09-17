<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\SentryBeforeSend;
use Illuminate\Database\QueryException;
use PDOException;
use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;
use Tests\TestCase;

class SentryBeforeSendTest extends TestCase
{
    public function test_drops_pgsql_hostname_translation_failures(): void
    {
        $exception = $this->queryException(
            'SQLSTATE[08006] [7] could not translate host name "pgsql" to address: Temporary failure in name resolution',
        );

        $this->assertTrue(SentryBeforeSend::isTransientPgsqlDnsFailure($exception));

        $hint = EventHint::fromArray(['exception' => $exception]);
        $event = Event::createEvent();

        $this->assertNull(SentryBeforeSend::beforeSend($event, $hint));
    }

    public function test_keeps_unrelated_query_exceptions(): void
    {
        $exception = $this->queryException(
            'SQLSTATE[23505]: Unique violation: 7 ERROR: duplicate key value violates unique constraint',
        );

        $this->assertFalse(SentryBeforeSend::isTransientPgsqlDnsFailure($exception));

        $hint = EventHint::fromArray(['exception' => $exception]);
        $event = Event::createEvent();

        $this->assertSame($event, SentryBeforeSend::beforeSend($event, $hint));
    }

    public function test_keeps_non_query_exceptions(): void
    {
        $this->assertFalse(
            SentryBeforeSend::isTransientPgsqlDnsFailure(new RuntimeException('boom')),
        );

        $hint = EventHint::fromArray(['exception' => new RuntimeException('boom')]);
        $event = Event::createEvent();

        $this->assertSame($event, SentryBeforeSend::beforeSend($event, $hint));
    }

    private function queryException(string $message): QueryException
    {
        return new QueryException(
            'pgsql',
            'select 1',
            [],
            new PDOException($message),
        );
    }
}
