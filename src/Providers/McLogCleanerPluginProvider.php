<?php

namespace JuggleGaming\McLogCleaner\Providers;

use App\Enums\HeaderActionPosition;
use App\Filament\Server\Pages\Console;
use App\Filament\Server\Resources\Files\Pages\EditFiles;
use App\Filament\Server\Resources\Files\Pages\ListFiles;
use Illuminate\Support\ServiceProvider;
use JuggleGaming\McLogCleaner\Filament\Components\Actions\McLogCleanAction;

class McLogCleanerPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        Console::registerCustomHeaderActions(HeaderActionPosition::Before, McLogCleanAction::make());
        EditFiles::registerCustomHeaderActions(HeaderActionPosition::Before, McLogCleanAction::make());
        ListFiles::registerCustomHeaderActions(HeaderActionPosition::Before, McLogCleanAction::make());
    }

    public function boot(): void
    {
        //
    }
}
