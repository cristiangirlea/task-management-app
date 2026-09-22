<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Carbon\CarbonInterface;
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

    /**
     * Past its due date and not finished. Mirrors isOverdue().
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->where('status', '!=', self::STATUS_COMPLETED);
    }

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDueBefore(Builder $query, CarbonInterface|string $date): Builder
    {
        return $query->whereNotNull('due_date')->where('due_date', '<', $date);
    }

    /**
     * Free-text match on title or description.
     *
     * Wildcards in the term are escaped so that searching for "100%" finds
     * that literal string rather than everything. The ESCAPE clause is
     * explicit because SQLite, unlike MySQL and Postgres, assigns no meaning
     * to a backslash in LIKE unless told to.
     */
    public function scopeMatching(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $inner) => $inner
            ->whereRaw('title LIKE ? ESCAPE ?', [$like, '\\'])
            ->orWhereRaw('description LIKE ? ESCAPE ?', [$like, '\\']));
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->status !== self::STATUS_COMPLETED;
    }
}
