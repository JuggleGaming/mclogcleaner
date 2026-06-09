<?php

namespace JuggleGaming\McLogCleaner\Filament\Components\Actions;

use App\Models\Server;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
            ->modalDescription(fn () =>trans('mclogcleaner::cleaner.button.delete_logs_description'))
            ->modalSubmitActionLabel(fn () =>trans('mclogcleaner::cleaner.button.delete_logs_label'))
            ->form([
                Select::make('mode')
                    ->label(fn () =>trans('mclogcleaner::cleaner.button.delete_logs_label'))
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
                    ->label(fn () =>trans('mclogcleaner::cleaner.button.delete_custom_label'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->placeholder('14')
                    ->required(fn ($get) => $get('mode') === 'custom')
                    ->visible(fn ($get) => $get('mode') === 'custom'),
            ]);

        $this->action(function (array $data) {
            /** @var Server|null $server */
            $server = Filament::getTenant();
            $mode = $data['mode'];
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
            try {
                $files = Http::daemon($server->node)
                    ->get("/api/servers/{$server->uuid}/files/list-directory", [
                        'directory' => 'logs',
                    ])
                    ->throw()
                    ->json();
                if (! is_array($files)) {
                    throw new Exception('Invalid log directory response.');
                }
                $threshold = now()->subDays($days)->startOfDay();
                $logsToDelete = collect($files)
                    ->filter(fn ($file) => str_ends_with($file['name'], '.log.gz'))
                    ->filter(function ($file) use ($days, $threshold) {
                        if ($days === 0) {
                            return true;
                        }
                        $logDate = $this->extractLogDate($file['name']);
                        if (! $logDate) {
                            return false;
                        }

                        return $logDate->lessThan($threshold);
                    })
                    ->pluck('name')
                    ->map(fn ($name) => 'logs/'.$name)
                    ->values()
                    ->all();
                if (empty($logsToDelete)) {
                    Notification::make()
                        ->title('McLogCleaner')
                        ->body(trans('mclogcleaner::cleaner.button.no_logs_found'),)
                        ->success()
                        ->send();

                    return;
                }
                Http::daemon($server->node)
                    ->post("/api/servers/{$server->uuid}/files/delete", [
                        'root' => '/',
                        'files' => $logsToDelete,
                    ])
                    ->throw();
                Notification::make()
                    ->title(trans('mclogcleaner::cleaner.button.cleanup_successful'))
                    ->body(count($logsToDelete).' '.trans('mclogcleaner::cleaner.button.files_deleted'))
                    ->success()
                    ->send();
            } catch (\Throwable $e) {
                report($e);
                Notification::make()
                    ->title(trans('mclogcleaner::cleaner.button.error_occured_label'),)
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
