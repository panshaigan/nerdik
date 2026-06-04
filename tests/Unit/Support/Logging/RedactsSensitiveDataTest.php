<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Logging;

use App\Support\Logging\RedactsSensitiveData;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class RedactsSensitiveDataTest extends TestCase
{
    #[Test]
    public function it_redacts_sensitive_keys_in_context_arrays(): void
    {
        $redacted = RedactsSensitiveData::redactArray([
            'userId' => 1,
            'session' => ['_token' => '51OzIWMXtwYvLreAWm1uZ06Jgb6OSd129NplObct'],
            'password' => 'hunter2',
        ]);

        $this->assertSame(1, $redacted['userId']);
        $this->assertSame('[REDACTED]', $redacted['session']);
        $this->assertSame('[REDACTED]', $redacted['password']);
    }

    #[Test]
    public function it_redacts_token_values_embedded_in_log_messages(): void
    {
        $message = '{"upload":[],"session":{"_token":"51OzIWMXtwYvLreAWm1uZ06Jgb6OSd129NplObct","locale":"en"}}';

        $redacted = RedactsSensitiveData::redactString($message);

        $this->assertStringContainsString('"_token":"[REDACTED]"', $redacted);
        $this->assertStringNotContainsString('51OzIWMXtwYvLreAWm1uZ06Jgb6OSd129NplObct', $redacted);
    }

    #[Test]
    public function it_identifies_sensitive_key_names(): void
    {
        $this->assertTrue(RedactsSensitiveData::isSensitiveKey('_token'));
        $this->assertTrue(RedactsSensitiveData::isSensitiveKey('current_password'));
        $this->assertFalse(RedactsSensitiveData::isSensitiveKey('locale'));
    }
}
