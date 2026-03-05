<?php

class WPNS_Loader
{
    private array $actions = [];
    private array $filters = [];

    /**
     * Queues a WordPress action registration for later registration.
     *
     * Stores the hook name, target component, callback method name, priority, and
     * accepted argument count so the action can be registered at a later time.
     *
     * @param string $hook The action hook name.
     * @param object|string $component The object instance or class name that contains the callback method.
     * @param string $callback The method name on the component to call when the hook is fired.
     * @param int $priority Execution priority for the hook; lower values run earlier.
     * @param int $args Number of arguments the callback accepts.
     */

    public function add_action(string $hook, $component, string $callback, int $priority = 10, int $args = 1): void
    {
        $this->actions[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args,
        ];
    }

    /**
     * Queues a filter registration for later registration with WordPress.
     *
     * Stores the provided hook, component, callback name, priority, and argument count in the loader's internal filter list so they can be registered when run() is invoked.
     *
     * @param string $hook The filter hook name.
     * @param mixed  $component The object or class that contains the callback method.
     * @param string $callback The method name to call on the component when the hook is triggered.
     * @param int    $priority The priority at which the callback should be executed.
     * @param int    $args The number of arguments the callback accepts.
     */
    public function add_filter(string $hook, $component, string $callback, int $priority = 10, int $args = 1): void
    {
        $this->filters[] = [
            'hook' => $hook,
            'component' => $component,
            'callback' => $callback,
            'priority' => $priority,
            'args' => $args,
        ];
    }

    /**
     * Registers all stored actions and filters with WordPress.
     *
     * Uses the loader's collected registrations to call WordPress's add_action and
     * add_filter for each entry, applying the stored hook name, component/callback
     * pair, priority, and accepted argument count.
     */
    public function run(): void
    {
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
