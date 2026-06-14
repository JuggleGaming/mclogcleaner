<?php

namespace JuggleGaming\McLogCleaner\Filament\Components\Actions;

use App\Models\Server;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Illuminate\Support\Facades\Http;

class McLogCleanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'clean_logs';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->hidden(function () {
            /** @var Server|null $server */
            $server = Filament::getTenant();
            if (! $server) {
                return true;
            }
            $server->loadMissing('egg');
            $features = $server->egg->features ?? [];

            return ! in_array('mclogcleaner', $features, true);
        });

        $this->label(function () {
            return config('mclogcleaner.mclogcleaner_text_enabled') ? trans('mclogcleaner::cleaner.button.delete_logs_label') : '';
        });

        $this->icon('tabler-trash');
        $this->color('danger');
        $this->size(Size::ExtraLarge);
        $this->requiresConfirmation()
            ->modalHeading('McLogCleaner')
            ->modalDescription(fn () => trans('mclogcleaner::cleaner.button.delete_logs_description'))
            ->modalSubmitActionLabel(fn () => trans('mclogcleaner::cleaner.button.delete_logs_label'))
            ->form([
                CheckboxList::make('targets')
                    ->label(fn () => trans('mclogcleaner::cleaner.button.delete_choose_label'))
                    ->options(fn () => [
                        'logs' => __('mclogcleaner::cleaner.button.delete_choose_logs'),
                        'crashes' => __('mclogcleaner::cleaner.button.delete_choose_crash'),
                    ])
                    ->default(['logs'])
                    ->required(),

                Select::make('mode')
                    ->label(fn () => trans('mclogcleaner::cleaner.button.delete_logs_label'))
                    ->options(fn () => [
                        7 => __('mclogcleaner::cleaner.button.delete_older_than_7'),
                        30 => __('mclogcleaner::cleaner.button.delete_older_than_30'),
                        -1 => __('mclogcleaner::cleaner.button.delete_all'),
                        'custom' => __('mclogcleaner::cleaner.button.delete_custom'),
                    ])
                    ->default(7)
                    ->required()
                    ->reactive(),

                TextInput::make('custom_days')
                    ->label(fn () => trans('mclogcleaner::cleaner.button.delete_custom_label'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(1095)
                    ->placeholder('14')
                    ->required(fn ($get) => $get('mode') === 'custom')
                    ->visible(fn ($get) => $get('mode') === 'custom'),

                Toggle::make('dry_run')
                    ->label(fn () => trans('mclogcleaner::cleaner.button.dryrun_toggle'))
                    ->helperText(fn () => trans('mclogcleaner::cleaner.button.dryrun_info'))
                    ->default(false),
            ]);

        $this->action(function (array $data) {
            /** @var Server|null $server */
            $server = Filament::getTenant();
            if (! $server) return;

            $mode = $data['mode'];
            $targets = $data['targets'];
            $isDryRun = $data['dry_run'] ?? false;

            if ($mode !== 'custom') {
                $mode = (int) $mode;
            }
            if ($mode === 'custom') {
                $days = max(1, (int) $data['custom_days']);
            } elseif ($mode === -1) {
                $days = 0;
            } else {
                $days = $mode;
            }

            $threshold = now()->subDays($days)->startOfDay();
            $filesToDelete = [];

            try {
                if (in_array('logs', $targets, true)) {
                    try {
                        $logFiles = Http::daemon($server->node)
                            ->get("/api/servers/{$server->uuid}/files/list-directory", [
                                'directory' => 'logs',
                            ])
                            ->throw()
                            ->json();
                        if (is_array($logFiles)) {
                            $filteredLogs = collect($logFiles)
                                ->filter(fn ($file) => str_ends_with($file['name'], '.log.gz'))
                                ->filter(function ($file) use ($days, $threshold) {
                                    if ($days === 0) return true;
                                    $logDate = $this->extractLogDate($file['name']);
                                    return $logDate ? $logDate->lessThan($threshold) : false;
                                })
                                ->pluck('name')
                                ->map(fn ($name) => 'logs/' . $name)
                                ->all();
                            $filesToDelete = array_merge($filesToDelete, $filteredLogs);
                        }
                    } catch (\Throwable $e) {
                    }
                }

                if (in_array('crashes', $targets, true)) {
                    try {
                        $crashFiles = Http::daemon($server->node)
                            ->get("/api/servers/{$server->uuid}/files/list-directory", [
                                'directory' => 'crash-reports',
                            ])
                            ->throw()
                            ->json();
                        if (is_array($crashFiles)) {
                            $filteredCrashes = collect($crashFiles)
                                ->filter(fn ($file) => str_starts_with($file['name'], 'crash-') && str_ends_with($file['name'], '.txt'))
                                ->filter(function ($file) use ($days, $threshold) {
                                    if ($days === 0) return true;
                                    $crashDate = $this->extractLogDate($file['name']);
                                    return $crashDate ? $crashDate->lessThan($threshold) : false;
                                })
                                ->pluck('name')
                                ->map(fn ($name) => 'crash-reports/' . $name)
                                ->all();

                            $filesToDelete = array_merge($filesToDelete, $filteredCrashes);
                        }
                    } catch (\Throwable $e) {
                    }
                }

                if (empty($filesToDelete)) {
                    Notification::make()
                        ->title('McLogCleaner')
                        ->body(trans('mclogcleaner::cleaner.button.no_logs_found'))
                        ->success()
                        ->send();
                    return;
                }

                if ($isDryRun) {
                    Notification::make()
                        ->title('McLogCleaner ' . trans('mclogcleaner::cleaner.button.dryrun_label'))
                        ->body(trans('mclogcleaner::cleaner.button.dryrun_successful', [
                            'count' => count($filesToDelete),
                        ]))
                        ->info()
                        ->send();
                    return;
                }

                Http::daemon($server->node)
                    ->post("/api/servers/{$server->uuid}/files/delete", [
                        'root' => '/',
                        'files' => $filesToDelete,
                    ])
                    ->throw();

                Notification::make()
                    ->title(trans('mclogcleaner::cleaner.button.cleanup_successful'))
                    ->body(count($filesToDelete) . ' ' . trans('mclogcleaner::cleaner.button.files_deleted'))
                    ->success()
                    ->send();

            } catch (\Throwable $e) {
                report($e);
                Notification::make()
                    ->title(trans('mclogcleaner::cleaner.button.error_occured_label'))
                    ->body(trans('mclogcleaner::cleaner.button.error_occured_description'))
                    ->danger()
                    ->send();
            }
        });
    }

    private function extractLogDate(string $filename): ?Carbon
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', $filename, $matches)) {
            $date = Carbon::createFromFormat('Y-m-d', $matches[1]);
            return $date ? $date->startOfDay() : null;
        }

        return null;
    }
}