<?php

namespace Tests\Feature\Config;

use Tests\TestCase;

class AppTrustedProxiesConfigTest extends TestCase
{
    /**
     * @param  array<string, string|null>  $overrides
     * @return array<string, mixed>
     */
    private function loadAppConfig(array $overrides = []): array
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

        $config = require config_path('app.php');

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

    public function test_trusted_proxies_config_reads_from_environment(): void
    {
        $config = $this->loadAppConfig([
            'TRUSTED_PROXIES' => '10.0.0.1, 10.0.0.2',
        ]);

        $this->assertSame('10.0.0.1, 10.0.0.2', $config['trusted_proxies']);
    }
}
