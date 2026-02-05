<?php

namespace JuggleGaming\McLogCleaner\Filament\Components\Actions;

use App\Models\Server;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Size;
use Illuminate\Support\Facades\Http;
use JuggleGaming\McLogCleaner\Enums\CheckEgg;


class McLogCleanAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'clean_logs';
    }

    protected function setUp(): void{
        parent::setUp();

        // Nur sichtbar, wenn der Server online ist

        $this->hidden(function () {
         /** @var Server $server */
             $server = Filament::getTenant();

            return
                $server->retrieveStatus()->isOffline()
                || ! CheckEgg::serverSupportsLogCleaner($server);
        });

        $this->label('Delete logs');
        $this->icon('tabler-trash');
        $this->color('danger');
        $this->size(Size::ExtraLarge);

        $this->action(function () {
            /** @var Server $server */
            $server = Filament::getTenant();

            try {
                $logs = Http::daemon($server->node)
                    ->get("/api/servers/{$server->uuid}/files/list-directory", [
                        'directory' => 'logs'
                    ])
                    ->throw()
                    ->json();

                if (!is_array($logs) || count($logs) === 0) {
                    throw new Exception('No logs found.');
                }

                $fileNames = array_map(fn($file) => $file['name'], $logs);
                $logsToDelete = array_filter($fileNames, fn($name) => str_ends_with($name, '.log.gz'));

                if (count($logsToDelete) === 0) {
                    Notification::make()
                        ->title('McLogCleaner')
                        ->body('No logs (.log.gz-files) found.')
                        ->success()
                        ->send();
                    return;
                }

                Http::daemon($server->node)
                    ->post("/api/servers/{$server->uuid}/files/delete", [
                        'root' => '/',
                        'files' => array_map(fn($name) => 'logs/' . $name, $logsToDelete),
                    ])
                    ->throw();

                Notification::make()
                    ->title('Logfolder cleaned')
                    ->body(count($logsToDelete) . ' files were deleted.')
                    ->success()
                    ->send();

            } catch (Exception $exception) {
                report($exception);

                Notification::make()
                    ->title('Cleanup failed.')
                    ->body($exception->getMessage())
                    ->danger()
                    ->send();
            }
        });
    }
}
