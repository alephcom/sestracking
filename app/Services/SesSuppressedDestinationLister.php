<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SesSuppressedDestination;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

final class SesSuppressedDestinationLister
{
    public const SORT_COLUMNS = ['email', 'reason', 'last_update_time'];

    private const PER_PAGE = 25;

    /**
     * @return array{sort: string, direction: string}
     */
    public static function nextSortQuery(Request $request, string $column): array
    {
        if (! in_array($column, self::SORT_COLUMNS, true)) {
            $column = 'last_update_time';
        }

        $current = $request->query('sort', 'last_update_time');
        $dir = strtolower((string) $request->query('direction', 'desc'));
        if ($current === $column) {
            return [
                'sort' => $column,
                'direction' => $dir === 'asc' ? 'desc' : 'asc',
            ];
        }

        return ['sort' => $column, 'direction' => 'asc'];
    }

    public function paginate(Project $project, Request $request): LengthAwarePaginator
    {
        $q = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'last_update_time');
        if (! in_array($sort, self::SORT_COLUMNS, true)) {
            $sort = 'last_update_time';
        }

        $direction = strtolower((string) $request->query('direction', 'desc'));
        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'desc';
        }

        $query = SesSuppressedDestination::query()->where('project_id', $project->id);

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where('email', 'like', $like);
        }

        $query->orderBy($sort, $direction);
        if ($sort !== 'email') {
            $query->orderBy('email', 'asc');
        }

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }
}
