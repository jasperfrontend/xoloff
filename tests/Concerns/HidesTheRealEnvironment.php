<?php

namespace Tests\Concerns;

/**
 * Blanks a set of environment keys for the duration of a test, and puts them
 * back afterwards.
 *
 * For tests that check how a config file maps environment keys, which cannot
 * be done against the container's copy - that was built once, from the real
 * environment, before any test ran. The file has to be re-evaluated, and the
 * environment it is evaluated against has to be one this test owns.
 *
 * Not a tidiness measure. An assertion that fails prints the value it found,
 * and some of these values are live credentials. It has to be impossible for a
 * broken test to put one on screen.
 *
 * Blanking means all three of $_ENV, $_SERVER and putenv. Laravel's env()
 * reads them in that order and stops at the first hit, so clearing only the
 * last leaves the real value in play - which is exactly the mistake this trait
 * exists to stop anyone repeating.
 */
trait HidesTheRealEnvironment
{
    /**
     * @var array<string, array{env: mixed, server: mixed, putenv: string|false}>
     */
    private array $realEnvironment = [];

    /**
     * @param  list<string>  $keys
     */
    protected function hideEnvironmentKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $this->realEnvironment[$key] = [
                'env' => $_ENV[$key] ?? null,
                'server' => $_SERVER[$key] ?? null,
                'putenv' => getenv($key),
            ];

            $this->forgetEnvironmentKey($key);
        }
    }

    protected function restoreTheRealEnvironment(): void
    {
        foreach ($this->realEnvironment as $key => $original) {
            $this->forgetEnvironmentKey($key);

            if ($original['env'] !== null) {
                $_ENV[$key] = $original['env'];
            }

            if ($original['server'] !== null) {
                $_SERVER[$key] = $original['server'];
            }

            if ($original['putenv'] !== false) {
                putenv("{$key}={$original['putenv']}");
            }
        }

        $this->realEnvironment = [];
    }

    protected function setEnvironmentKey(string $key, string $value): void
    {
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    }

    /**
     * Re-evaluated rather than read from the container, for the reason in the
     * class comment.
     *
     * @return array<string, mixed>
     */
    protected function freshConfig(string $file): array
    {
        return require base_path("config/{$file}.php");
    }

    private function forgetEnvironmentKey(string $key): void
    {
        unset($_ENV[$key], $_SERVER[$key]);
        putenv($key);
    }
}
