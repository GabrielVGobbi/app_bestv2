<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

class ActivityLogApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Activity::query()
            ->with(['causer', 'subject'])
            ->orderBy('created_at', 'desc');

        $search = $request->string('search')->value() ?: null;

        if ($search) {
            $query->where(function ($q) use ($search): void {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('event', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%");
            });
        }

        $filters = $request->array('filter');

        foreach ($filters as $column => $value) {
            $this->applyFilter($query, $column, $value);
        }

        $perPage = min($request->integer('per_page', 15), 100);

        return ActivityLogResource::collection(
            $query->paginate($perPage)->withQueryString()
        );
    }

    private function applyFilter(Builder|QueryBuilder $query, string $column, mixed $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        // Structured filter with operator
        if (is_array($value) && array_key_exists('operator', $value)) {
            $operator = Str::lower((string) ($value['operator'] ?? 'eq'));
            $filterValue = $value['value'] ?? null;

            $this->applyOperatorFilter($query, $column, $operator, $filterValue);

            return;
        }

        match ($column) {
            'log_name' => $this->applySimpleFilter($query, 'log_name', $value),
            'event' => $this->applySimpleFilter($query, 'event', $value),
            'action_key' => $this->applyActionKeyFilter($query, $value),
            'causer_id' => $this->applySimpleFilter($query, 'causer_id', $value),
            'subject_type' => $query->where('subject_type', 'like', "%{$value}%"),
            'causer_name' => $this->applyCauserNameFilter($query, $value),
            'created_at' => $this->applyBetweenFilter($query, 'created_at', $value),
            default => null,
        };
    }

    private function applyOperatorFilter(Builder $query, string $column, string $operator, mixed $value): void
    {
        $allowedColumns = ['log_name', 'event', 'created_at', 'subject_type', 'action_key'];

        if (! in_array($column, $allowedColumns, true)) {
            return;
        }

        if ($column === 'action_key') {
            $this->applyActionKeyFilter($query, $value);

            return;
        }

        match ($operator) {
            'contains' => $query->where($column, 'like', "%{$value}%"),
            'between' => $this->applyBetweenFilter($query, $column, $value),
            'gte' => $query->where($column, '>=', $value),
            'lte' => $query->where($column, '<=', $value),
            'in' => is_array($value) ? $query->whereIn($column, array_filter($value, fn ($v) => $v !== null && $v !== '')) : null,
            default => $query->where($column, '=', $value),
        };
    }

    /**
     * @param  mixed  $value
     */
    private function applySimpleFilter(Builder $query, string $column, $value): void
    {
        if (is_array($value)) {
            $values = array_filter($value, fn ($v) => $v !== null && $v !== '');
            if ($values !== []) {
                $query->whereIn($column, array_values($values));
            }

            return;
        }

        $query->where($column, $value);
    }

    /**
     * @param  array<string, mixed>|mixed  $value
     */
    private function applyBetweenFilter(Builder $query, string $column, mixed $value): void
    {
        if (! is_array($value)) {
            return;
        }

        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if ($from !== null && $from !== '') {
            $query->where($column, '>=', $from);
        }

        if ($to !== null && $to !== '') {
            $query->where($column, '<=', $to);
        }
    }

    private function applyCauserNameFilter(Builder $query, string $value): void
    {
        $query->whereHasMorph('causer', [User::class], function ($q) use ($value): void {
            $q->where('name', 'like', "%{$value}%")
                ->orWhere('email', 'like', "%{$value}%");
        });
    }

    private function applyActionKeyFilter(Builder $query, mixed $value): void
    {
        if (is_array($value)) {
            $values = array_values(array_filter($value, fn ($v) => is_string($v) && $v !== ''));

            if ($values !== []) {
                $query->whereIn('properties->action_key', $values);
            }

            return;
        }

        if (is_string($value) && $value !== '') {
            $query->where('properties->action_key', $value);
        }
    }
}
