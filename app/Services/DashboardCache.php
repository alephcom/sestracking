<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Caches dashboard API data, tagged per project so that new webhook events can
 * invalidate just the affected projects.
 *
 * Tags need a cache store that supports them (Redis, Memcached, array). Other
 * stores (file, database) fall back to the plain TTL with no early invalidation.
 */
class DashboardCache
{
    /**
     * @param  array<int>  $projectIds
     */
    public function remember(array $projectIds, string $key, int $ttlSeconds, Closure $callback): mixed
    {
        if (! Cache::supportsTags()) {
            return Cache::remember($key, $ttlSeconds, $callback);
        }

        return Cache::tags($this->tagsFor($projectIds))->remember($key, $ttlSeconds, $callback);
    }

    public function flushProject(int $projectId): void
    {
        if (Cache::supportsTags()) {
            Cache::tags($this->tagsFor([$projectId]))->flush();
        }
    }

    /**
     * @param  array<int>  $projectIds
     * @return list<string>
     */
    private function tagsFor(array $projectIds): array
    {
        return array_values(array_map(fn ($id) => 'dashboard-project-'.(int) $id, $projectIds));
    }
}
