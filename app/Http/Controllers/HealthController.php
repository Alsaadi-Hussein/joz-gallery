<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * For uptime monitors. Anyone gets one word and a status code. The figures need
 * HEALTH_TOKEN, because they describe the server to whoever can read them.
 */
class HealthController extends Controller
{
    public const SCHEDULER_KEY = 'health.scheduler';

    // Cron runs every minute, so this long without a beat means it has stopped.
    private const SCHEDULER_STALE_SECONDS = 300;

    private const DISK_FULL_PERCENT = 90;

    private const BACKUP_FOLDER = 'joz-gallery';

    public function __invoke(Request $request): JsonResponse
    {
        $database = $this->database();
        $headers = ['Cache-Control' => 'no-store'];

        if (! $database['ok']) {
            return response()->json($this->isTrusted($request) ? ['status' => 'down', 'database' => $database] : ['status' => 'down'], 503, $headers);
        }

        if (! $this->isTrusted($request)) {
            return response()->json(['status' => 'ok'], 200, $headers);
        }

        $scheduler = $this->scheduler();
        $disk = $this->disk();
        $healthy = $scheduler['ok'] && $disk['ok'] && $this->storageIsWritable();

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'database' => $database,
            'memory' => $this->memory(),
            'disk' => $disk,
            'uptime' => $this->uptime(),
            'scheduler' => $scheduler,
            'queue' => $this->queue(),
            'backup' => $this->backup(),
            'storage_writable' => $this->storageIsWritable(),
            'php' => PHP_VERSION,
            'environment' => app()->environment(),
        ], 200, $headers);
    }

    private function isTrusted(Request $request): bool
    {
        $token = (string) config('joz.health_token');

        return $token !== '' && hash_equals($token, (string) $request->header('X-Health-Token'));
    }

    /**
     * @return array{ok: bool, latency_ms: float|null}
     */
    private function database(): array
    {
        $started = hrtime(true);

        try {
            DB::select('select 1');
        } catch (Throwable) {
            return ['ok' => false, 'latency_ms' => null];
        }

        return ['ok' => true, 'latency_ms' => round((hrtime(true) - $started) / 1e6, 1)];
    }

    /**
     * @return array{current_mb: float, peak_mb: float, limit: string|false}
     */
    private function memory(): array
    {
        return [
            'current_mb' => round(memory_get_usage(true) / 1048576, 1),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 1),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Hosts can turn these functions off, which must not look like a full disk.
     *
     * @return array{ok: bool, free_mb: float|null, total_mb: float|null, used_percent: float|null}
     */
    private function disk(): array
    {
        $free = function_exists('disk_free_space') ? @disk_free_space(base_path()) : false;
        $total = function_exists('disk_total_space') ? @disk_total_space(base_path()) : false;

        if (! $free || ! $total) {
            return ['ok' => true, 'free_mb' => null, 'total_mb' => null, 'used_percent' => null];
        }

        $used = round(100 - $free / $total * 100, 1);

        return [
            'ok' => $used < self::DISK_FULL_PERCENT,
            'free_mb' => round($free / 1048576),
            'total_mb' => round($total / 1048576),
            'used_percent' => $used,
        ];
    }

    /**
     * PHP keeps no running process here, so uptime counts from the last deployment.
     *
     * @return array{deployed_at: string|null, seconds: int|null, revision: string|null}
     */
    private function uptime(): array
    {
        $marker = base_path('REVISION');
        $since = @filemtime($marker) ?: @filemtime(app()->getCachedConfigPath());

        return [
            'deployed_at' => $since ? date(DATE_ATOM, $since) : null,
            'seconds' => $since ? time() - $since : null,
            'revision' => File::exists($marker) ? substr(trim(File::get($marker)), 0, 12) : null,
        ];
    }

    /**
     * @return array{ok: bool, last_run_seconds_ago: int|null}
     */
    private function scheduler(): array
    {
        $lastRun = Cache::get(self::SCHEDULER_KEY);
        $age = $lastRun ? time() - (int) $lastRun : null;

        return ['ok' => $age !== null && $age < self::SCHEDULER_STALE_SECONDS, 'last_run_seconds_ago' => $age];
    }

    /**
     * @return array{waiting: int, failed: int}
     */
    private function queue(): array
    {
        return ['waiting' => DB::table('jobs')->count(), 'failed' => DB::table('failed_jobs')->count()];
    }

    /**
     * @return array{count: int, newest_hours_ago: float|null}
     */
    private function backup(): array
    {
        $disk = Storage::disk('local');
        $files = $disk->files(self::BACKUP_FOLDER);
        $newest = collect($files)->map(fn (string $file) => $disk->lastModified($file))->max();

        return ['count' => count($files), 'newest_hours_ago' => $newest ? round((time() - $newest) / 3600, 1) : null];
    }

    private function storageIsWritable(): bool
    {
        return is_writable(storage_path('framework/cache')) && is_writable(storage_path('logs'));
    }
}
