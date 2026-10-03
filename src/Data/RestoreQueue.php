<?php

declare(strict_types=1);

namespace Itxshakil\CpanelWhm\Data;

/**
 * WHM's account restore queue, from restore_queue_state.
 */
final readonly class RestoreQueue
{
    /**
     * @param  list<RestoreTask>  $pending
     * @param  list<RestoreTask>  $active
     * @param  list<RestoreTask>  $completed
     */
    public function __construct(
        public bool $running,
        public array $pending,
        public array $active,
        public array $completed,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $tasks = static function (mixed $rows): array {
            $tasks = [];

            foreach (is_array($rows) ? $rows : [] as $row) {
                if (is_array($row)) {
                    $tasks[] = RestoreTask::fromArray($row);
                }
            }

            return $tasks;
        };

        return new self(
            running: Value::bool($data['is_active'] ?? false),
            pending: $tasks($data['pending'] ?? null),
            active: $tasks($data['active'] ?? null),
            completed: $tasks($data['completed'] ?? null),
        );
    }

    /**
     * Where a user's restoration is: pending, active, completed, or null when it is not queued.
     */
    public function stateOf(string $user): ?string
    {
        foreach (['active' => $this->active, 'pending' => $this->pending, 'completed' => $this->completed] as $state => $tasks) {
            foreach ($tasks as $task) {
                if ($task->user === $user) {
                    return $state;
                }
            }
        }

        return null;
    }
}
