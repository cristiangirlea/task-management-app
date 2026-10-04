<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * A project's tasks changed (created, edited, moved, reordered or deleted).
 * Open boards reload the project's tasks through the API, so the message
 * carries nothing but the project.
 */
class BoardChanged implements ShouldBroadcastNow
{
    use InteractsWithSockets;

    public function __construct(public readonly int $projectId) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("projects.{$this->projectId}");
    }

    public function broadcastAs(): string
    {
        return 'board.changed';
    }

    /**
     * @return array{project_id: int}
     */
    public function broadcastWith(): array
    {
        return ['project_id' => $this->projectId];
    }
}
