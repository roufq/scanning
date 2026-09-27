<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

#[Signature('analyzer:serve {--port=8001 : The port the analyzer listens on}')]
#[Description('Run the Python screenshot analyzer service')]
class ServeAnalyzer extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $python = PHP_OS_FAMILY === 'Windows'
            ? base_path('analyzer/.venv/Scripts/python.exe')
            : base_path('analyzer/.venv/bin/python');

        if (! is_file($python)) {
            $this->error('Python virtualenv not found. Create it with: python -m venv analyzer/.venv && analyzer/.venv/Scripts/pip install -r analyzer/requirements.txt');

            return self::FAILURE;
        }

        $result = Process::forever()
            ->path(base_path('analyzer'))
            ->env(['ANALYZER_STORAGE_ROOT' => Storage::disk('local')->path('')])
            ->run(
                [$python, '-m', 'uvicorn', 'app.main:app', '--host', '127.0.0.1', '--port', (string) $this->option('port')],
                fn (string $type, string $output) => $this->output->write($output),
            );

        return $result->exitCode() ?? self::FAILURE;
    }
}
