<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
    ];

    protected $attributes = [
        'status' => self::STATUS_PENDING,
        'priority' => 3,
        'position' => 0,
    ];

    protected $fillable = [
        'tenant_id',
        'project_id',
        'user_id',
        'title',
        'description',
        'status',
        'priority',
        'position',
        'due_date',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'priority' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Fall back to the project's tenant when no authenticated user set it
        // (factories, console commands).
        static::creating(function (Task $task) {
            if (empty($task->tenant_id) && $task->project_id) {
                $task->tenant_id = Project::withoutGlobalScopes()->whereKey($task->project_id)->value('tenant_id');
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The assignee, if any.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority(Builder $query, int $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    public function scopeForProject(Builder $query, int $projectId): Builder
    {
        return $query->where('project_id', $projectId);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->status !== self::STATUS_COMPLETED;
    }
}
