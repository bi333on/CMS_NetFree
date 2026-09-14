<?php

declare(strict_types=1);

namespace NetFree;

/**
 * Система хуков в стиле WordPress: actions и filters с приоритетами.
 */
class Hooks
{
    protected array $actions = [];
    protected array $filters = [];

    public function addAction(string $tag, callable $cb, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->actions[$tag][$priority][] = ['cb' => $cb, 'args' => $acceptedArgs];
    }

    public function addFilter(string $tag, callable $cb, int $priority = 10, int $acceptedArgs = 1): void
    {
        $this->filters[$tag][$priority][] = ['cb' => $cb, 'args' => $acceptedArgs];
    }

    public function doAction(string $tag, ...$args): void
    {
        foreach ($this->sorted($this->actions[$tag] ?? []) as $entry) {
            call_user_func_array($entry['cb'], array_slice($args, 0, max(1, (int) $entry['args'])));
        }
    }

    public function applyFilters(string $tag, $value, ...$args)
    {
        foreach ($this->sorted($this->filters[$tag] ?? []) as $entry) {
            $accepted = max(1, (int) $entry['args']);
            $callArgs = array_merge([$value], array_slice($args, 0, $accepted - 1));
            $value = call_user_func_array($entry['cb'], $callArgs);
        }
        return $value;
    }

    public function hasAction(string $tag): bool
    {
        return !empty($this->actions[$tag]);
    }

    public function hasFilter(string $tag): bool
    {
        return !empty($this->filters[$tag]);
    }

    protected function sorted(array $byPriority): array
    {
        ksort($byPriority);
        $out = [];
        foreach ($byPriority as $items) {
            $out = array_merge($out, $items);
        }
        return $out;
    }
}
