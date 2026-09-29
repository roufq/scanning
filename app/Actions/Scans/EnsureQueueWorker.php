<?php

namespace App\Actions\Scans;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Starts a background `queue:work` process when no worker is running, so scans get analyzed
 * even when the app is served by Laragon/Apache without a separately started worker.
 * The worker exits by itself once the queue is empty.
 */
class EnsureQueueWorker
{
    /**
     * Cache key refreshed by running workers (see AppServiceProvider).
     */
    public const HEARTBEAT_KEY = 'scanning:queue-worker-heartbeat';

    /**
     * Cache key set while a worker is busy with a (possibly long) job.
     */
    public const BUSY_KEY = 'scanning:queue-worker-busy';

    /**
     * Seconds a heartbeat counts as "a worker is alive".
     */
    protected const HEARTBEAT_SECONDS = 15;

    /**
     * Minimum seconds between two automatic starts.
     */
    protected const COOLDOWN_SECONDS = 20;

    /**
     * Start a worker if none is alive. Returns whether a worker was started.
     */
    public function handle(): bool
    {
        if (! config('scanning.auto_start_worker') || app()->runningUnitTests() || $this->workerIsAlive()) {
            return false;
        }

        if (! Cache::add('scanning:queue-worker-starting', true, self::COOLDOWN_SECONDS)) {
            return false;
        }

        $php = $this->phpBinary();

        if ($php === null) {
            Log::warning('Cannot auto-start the queue worker: php CLI binary not found. Set SCANNING_PHP_BINARY.');

            return false;
        }

        try {
            $this->spawn($php);
        } catch (Throwable $exception) {
            Log::warning('Cannot auto-start the queue worker.', ['exception' => $exception]);

            return false;
        }

        return true;
    }

    /**
     * Whether a worker reported in recently or is busy with a job.
     */
    public function workerIsAlive(): bool
    {
        if (Cache::has(self::BUSY_KEY)) {
            return true;
        }

        $heartbeat = Cache::get(self::HEARTBEAT_KEY);

        return is_int($heartbeat) && $heartbeat >= now()->getTimestamp() - self::HEARTBEAT_SECONDS;
    }

    /**
     * Find the PHP command-line binary (the web server may run php-cgi or a module).
     */
    protected function phpBinary(): ?string
    {
        $windows = PHP_OS_FAMILY === 'Windows';
        $name = $windows ? 'php.exe' : 'php';

        $candidates = array_filter([
            config('scanning.php_binary'),
            dirname(PHP_BINARY).DIRECTORY_SEPARATOR.$name,
            php_ini_loaded_file() ? dirname((string) php_ini_loaded_file()).DIRECTORY_SEPARATOR.$name : null,
            PHP_BINDIR.DIRECTORY_SEPARATOR.$name,
        ]);

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Launch a detached worker that stops once the queue is empty.
     */
    protected function spawn(string $php): void
    {
        $arguments = sprintf(
            '%s %s queue:work --stop-when-empty --tries=3 --timeout=900',
            escapeshellarg($php),
            escapeshellarg(base_path('artisan')),
        );

        if (PHP_OS_FAMILY === 'Windows') {
            $handle = popen('start "scan-worker" /B '.$arguments.' > NUL 2>&1', 'r');

            if ($handle === false) {
                throw new RuntimeException('popen() failed to start the queue worker.');
            }

            pclose($handle);

            return;
        }

        exec('nohup '.$arguments.' > /dev/null 2>&1 &');
    }
}
