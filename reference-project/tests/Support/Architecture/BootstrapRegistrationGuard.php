<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use Tests\Support\Globs;

/**
 * What the bootstrap file and the providers register: listeners are found by discovery (no provider imports a listener of a module), and the tenant
 * middleware pair sits in one group.
 */
final readonly class BootstrapRegistrationGuard
{
    /**
     * @param string $providers the provider folder, relative to the root
     * @param string $bootstrap the bootstrap file, relative to the root
     * @param string $listeners the namespace pattern of a module's listeners, as a regular expression without delimiters
     * @param string $discovery the discovery call that the bootstrap file must hold
     * @param list<string> $tenantPair the two class constants of the tenant middleware pair, each of which is named once
     * @param string $tenantGroup the name of the group that holds the pair
     */
    public function __construct(
        private string $root,
        private string $providers,
        private string $bootstrap,
        private string $listeners,
        private string $discovery,
        private array $tenantPair,
        private string $tenantGroup,
    ) {
    }

    /**
     * @return list<string>
     */
    public function violations(): array
    {
        $violations = [];

        foreach (Globs::of($this->root . '/' . $this->providers . '/*.php') as $file) {
            foreach ($this->providerInSource((string) file_get_contents($file)) as $message) {
                $violations[] = substr($file, strlen($this->root) + 1) . ': ' . $message;
            }
        }

        $file = $this->root . '/' . $this->bootstrap;

        if (! is_file($file)) {
            return [...$violations, $this->bootstrap . ': the bootstrap file does not exist: the guard has read nothing'];
        }

        foreach ($this->bootstrapInSource((string) file_get_contents($file)) as $message) {
            $violations[] = $this->bootstrap . ': ' . $message;
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    public function providerInSource(string $source): array
    {
        if (preg_match('/^use ' . $this->listeners . '/m', $source) !== 1) {
            return [];
        }

        return ['a provider imports a listener of a module: listeners are found by discovery, so a new listener needs no line in a provider'];
    }

    /**
     * @return list<string>
     */
    public function bootstrapInSource(string $source): array
    {
        $messages = [];

        if (! str_contains($source, $this->discovery)) {
            $messages[] = 'the discovery of listeners is gone: add ' . $this->discovery . ', or a listener is never registered';
        }

        foreach ($this->tenantPair as $middleware) {
            if (substr_count($source, $middleware) !== 1) {
                $messages[] = $middleware . ' is named ' . substr_count($source, $middleware) . ' times: the tenant middleware pair sits in one group, each named once';
            }
        }

        if (! str_contains($source, "\$middleware->group('" . $this->tenantGroup . "', [")) {
            $messages[] = 'the group ' . $this->tenantGroup . ' is gone: put the tenant middleware pair in $middleware->group(\'' . $this->tenantGroup . '\', [...])';
        }

        return $messages;
    }
}
