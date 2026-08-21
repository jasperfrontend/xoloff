<?php

namespace App\Support\Logo;

/**
 * Turns a hostname into the addresses it points at.
 *
 * A class of its own for one line of code, because it is the one part of the
 * logo fetcher that reaches outside the process. Without this seam the guard
 * around it could only be tested against hostnames that really resolve, which
 * would mean either trusting DNS in CI or not testing the guard at all - and
 * the guard is the part worth testing.
 */
class HostResolver
{
    /**
     * @return list<string> every address the host resolves to, empty if none
     */
    public function resolve(string $host): array
    {
        return array_values(array_filter((array) gethostbynamel($host)));
    }
}
