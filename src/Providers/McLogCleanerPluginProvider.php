<?php

namespace JuggleGaming\McLogCleaner\Providers;

use App\Enums\HeaderActionPosition;
use App\Filament\Server\Pages\Console;
use App\Filament\Server\Resources\Files\Pages\ListFiles;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use JuggleGaming\McLogCleaner\Filament\Components\Actions\McLogCleanAction;

class McLogCleanerPluginProvider extends ServiceProvider
{
    public function register(): void
    {
        Console::registerCustomHeaderActions(HeaderActionPosition::Before, McLogCleanAction::make());
        ListFiles::registerCustomHeaderActions(
            HeaderActionPosition::Before,
            McLogCleanAction::make()
                ->visible(function ($livewire) {
                    if (isset($livewire->path)) {
                        return $livewire->path === 'logs' || Str::startsWith($livewire->path, 'logs/');
                    }
                    return false;
                })
        );

    }

    public function boot(): void
    {
        //
    }
}
