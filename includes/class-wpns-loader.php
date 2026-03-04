<?php

class WPNS_Loader {
    private array $actions = [];
    private array $filters = [];

    public function add_action(string $hook, $component, string $callback, int $priority = 10, int $args = 1): void {
        $this->actions[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args,
        ];
    }

    public function add_filter(string $hook, $component, string $callback, int $priority = 10, int $args = 1): void {
        $this->filters[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args,
        ];
    }

    public function run(): void {
        foreach ($this->actions as $action) {
            add_action(
                $action['hook'],
                [$action['component'], $action['callback']],
                $action['priority'],
                $action['args']
            );
        }

        foreach ($this->filters as $filter) {
            add_filter(
                $filter['hook'],
                [$filter['component'], $filter['callback']],
                $filter['priority'],
                $filter['args']
            );
        }
    }
}
