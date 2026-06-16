<?php

namespace JuggleGaming\McLogCleaner\Console\Commands;

use App\Models\Server;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CleanLogsCommand extends Command
{
    protected $signature = 'mclogcleaner:clean
                            {server_uuid : The UUID of the server}
                            {--days=7 : Only process files older than this number of days}
                            {--logs : Only delete log files}
                            {--crashes : Only delete crash reports}
                            {--dry-run : Simulate cleanup without deleting files}';

    protected $description = 'Cleans up old Minecraft logs and crash reports for a specific server using its UUID';

    public function handle(): int
    {
        $serverUuid = $this->argument('server_uuid');
        $days = (int) $this->option('days');
        if ($days < 0) {
            $this->error('--days must be 0 or greater.');
            return Command::FAILURE;
        }
        $isDryRun = $this->option('dry-run');

        $cleanLogs = $this->option('logs');
        $cleanCrashes = $this->option('crashes');

        if (! $cleanLogs && ! $cleanCrashes) {
            $cleanLogs = true;
            $cleanCrashes = true;
        }

        $server = Server::where('uuid', $serverUuid)->first();

        if (! $server) {
            $this->error("Server with UUID '{$serverUuid}' was not found.");
            return Command::FAILURE;
        }

        $threshold = now()->subDays($days)->startOfDay();

        $targets = [];

        if ($cleanLogs) {
            $targets[] = 'Logs';
        }

        if ($cleanCrashes) {
            $targets[] = 'Crash Reports';
        }

        $this->info(sprintf(
            "Searching %s older than %d days on server '%s'...",
            implode(' and ', $targets),
            $days,
            $server->name
        ));

        try {
            $filesToDelete = [];

            if ($cleanLogs) {
                try {
                    $logFiles = Http::daemon($server->node)
                        ->get("/api/servers/{$server->uuid}/files/list-directory", [
                            'directory' => 'logs',
                        ])
                        ->throw()
                        ->json();

                    if (is_array($logFiles)) {
                        $filesToDelete = array_merge(
                            $filesToDelete,
                            collect($logFiles)
                                ->filter(fn ($file) => str_ends_with($file['name'], '.log.gz'))
                                ->filter(function ($file) use ($threshold) {
                                    $date = $this->extractDate($file['name']);
                                    return $date && $date->lessThan($threshold);
                                })
                                ->map(fn ($file) => 'logs/' . $file['name'])
                                ->all()
                        );
                    }
                } catch (\Throwable $e) {
                    $this->warn('Could not read logs: ' . $e->getMessage());
                }
            }

            if ($cleanCrashes) {
                try {
                    $crashFiles = Http::daemon($server->node)
                        ->get("/api/servers/{$server->uuid}/files/list-directory", [
                            'directory' => 'crash-reports',
                        ])
                        ->throw()
                        ->json();

                    if (is_array($crashFiles)) {
                        $filesToDelete = array_merge(
                            $filesToDelete,
                            collect($crashFiles)
                                ->filter(fn ($file) =>
                                    str_starts_with($file['name'], 'crash-')
                                    && str_ends_with($file['name'], '.txt')
                                )
                                ->filter(function ($file) use ($threshold) {
                                    $date = $this->extractDate($file['name']);
                                    return $date && $date->lessThan($threshold);
                                })
                                ->map(fn ($file) => 'crash-reports/' . $file['name'])
                                ->all()
                        );
                    }
                } catch (\Throwable $e) {
                    $this->warn('Could not read crash reports: ' . $e->getMessage());
                }
            }

            $count = count($filesToDelete);

            if ($count === 0) {
                $this->info('No files were found, that could be deleted.');
                return Command::SUCCESS;
            }

            if ($isDryRun) {
                $this->warn("[Dry-Run] Would delete {$count} files.");
                $this->newLine();
                foreach ($filesToDelete as $file) {
                    $this->line(" - {$file}");
                }
                return Command::SUCCESS;
            }

            $this->info('Deleting the following files:');

            foreach ($filesToDelete as $file) {
                $this->line(" - {$file}");
            }

            Http::daemon($server->node)
                ->post("/api/servers/{$server->uuid}/files/delete", [
                    'root' => '/',
                    'files' => $filesToDelete,
                ])
                ->throw();

            $this->newLine();
            $this->info("Successfully deleted {$count} files.");

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            report($e);

            $this->error('Error while cleaning files:');
            $this->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function extractDate(string $filename): ?Carbon
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
            return Carbon::createFromFormat('Y-m-d', $matches[1])->startOfDay();
        }

        return null;
    }
}