<?php
namespace WPForge\Core;

/**
 * Simple service container.
 */
class Container
{
    /** @var array<string, mixed> */
    private array $services = [];

    /** @var array<string, mixed> Singleton cache. */
    private array $shared = [];

    /** Services that should be treated as singletons. */
    private const SHARED = ['logger', 'config'];

    public function set(string $name, mixed $service): void
    {
        $this->services[$name] = $service;

        if (in_array($name, self::SHARED, true)) {
            $this->shared[$name] = $service;
        }
    }

    public function get(string $name): mixed
    {
        if (isset($this->shared[$name])) {
            return $this->shared[$name];
        }

        if (isset($this->services[$name])) {
            $service = $this->services[$name];

            if (is_callable($service)) {
                $service = $service($this);
            }

            if (in_array($name, self::SHARED, true)) {
                $this->shared[$name] = $service;
            }

            return $service;
        }

        return null;
    }

    public function has(string $name): bool
    {
        return isset($this->services[$name]) || isset($this->shared[$name]);
    }

    public function remove(string $name): void
    {
        unset($this->services[$name], $this->shared[$name]);
    }
}
