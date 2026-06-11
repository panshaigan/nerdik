<?php

namespace Tests\Feature\Config;

use Tests\TestCase;

class StagingMailConfigTest extends TestCase
{
    /**
     * @param  array<string, string|null>  $overrides
     * @return array<string, mixed>
     */
    private function loadMailConfig(array $overrides = []): array
    {
        $previous = [];

        foreach ($overrides as $key => $value) {
            $previous[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'getenv' => getenv($key) !== false ? getenv($key) : null,
            ];

            if ($value === null) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            } else {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }

        $config = require config_path('mail.php');

        foreach ($previous as $key => $values) {
            if ($values['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $values['env'];
            }

            if ($values['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $values['server'];
            }

            if ($values['getenv'] === null) {
                putenv($key);
            } else {
                putenv("{$key}={$values['getenv']}");
            }
        }

        return $config;
    }

    public function test_staging_mail_config_forces_mailpit(): void
    {
        $config = $this->loadMailConfig([
            'APP_ENV' => 'staging',
            'MAIL_HOST' => 'smtp.evil.example',
            'MAIL_PORT' => '587',
            'MAIL_SCHEME' => 'tls',
            'MAIL_USERNAME' => 'user',
            'MAIL_PASSWORD' => 'secret',
        ]);

        $this->assertSame('mailpit', $config['mailers']['smtp']['host']);
        $this->assertSame(1025, $config['mailers']['smtp']['port']);
        $this->assertNull($config['mailers']['smtp']['scheme']);
        $this->assertNull($config['mailers']['smtp']['username']);
        $this->assertNull($config['mailers']['smtp']['password']);
    }

    public function test_production_mail_config_reads_from_env(): void
    {
        $config = $this->loadMailConfig([
            'APP_ENV' => 'production',
            'MAIL_HOST' => 'mail.example.com',
            'MAIL_PORT' => '587',
            'MAIL_SCHEME' => 'tls',
            'MAIL_USERNAME' => 'user',
            'MAIL_PASSWORD' => 'secret',
        ]);

        $this->assertSame('mail.example.com', $config['mailers']['smtp']['host']);
        $this->assertSame(587, $config['mailers']['smtp']['port']);
        $this->assertSame('tls', $config['mailers']['smtp']['scheme']);
        $this->assertSame('user', $config['mailers']['smtp']['username']);
        $this->assertSame('secret', $config['mailers']['smtp']['password']);
    }
}
