<?php

namespace App\Http\Requests\Task;

use App\Models\Task;
use App\Validation\TaskRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTasksRequest extends FormRequest
{
    /**
     * Highest `limit` a caller may ask for. Omitting it returns every match,
     * which is what the board needs for a single project.
     */
    public const MAX_LIMIT = 500;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * A query string carries "true"/"false", which the boolean rule rejects.
     * Normalise the recognised spellings first; anything unrecognised is left
     * alone so validation still refuses it.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('overdue')) {
            return;
        }

        $value = filter_var($this->input('overdue'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        if ($value !== null) {
            $this->merge(['overdue' => $value]);
        }
    }

    public function rules(): array
    {
        $tenantId = $this->user()->tenant_id;

        return [
            'project_id' => ['nullable', 'integer', TaskRules::projectExists($tenantId)],
            'status' => ['nullable', Rule::in(Task::STATUSES)],
            'assigned_to' => ['nullable', 'integer', TaskRules::userExists($tenantId)],
            'overdue' => ['nullable', 'boolean'],
            'due_before' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
        ];
    }

    public function messages(): array
    {
        return TaskRules::messages();
    }

    /**
     * The validated input as repository filters, with query-string values
     * cast properly ("false" arrives as a non-empty string otherwise).
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        $this->validated();

        return [
            'project_id' => $this->integer('project_id') ?: null,
            'status' => $this->input('status'),
            'assigned_to' => $this->integer('assigned_to') ?: null,
            'overdue' => $this->boolean('overdue'),
            'due_before' => $this->input('due_before'),
            'search' => $this->input('search'),
            'limit' => $this->integer('limit') ?: null,
        ];
    }
}
