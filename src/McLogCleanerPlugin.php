<?php

namespace JuggleGaming\McLogCleaner;

use Filament\Contracts\Plugin;
use Filament\Notifications\Notification;
use Filament\Panel;

class McLogCleanerPlugin implements Plugin
{
    public function getId(): string
    {
        return 'mclogcleaner';
    }

    public function register(Panel $panel): void
    {
        //
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public function getSettingsForm(): array
    {
        return [
            TextInput::make('mclogcleaner_text_enabled')
                ->label(trans('mclogcleaner::strings.settings.mclogcleaner_text_enabled'))
                ->required()
                ->default(fn () => config('mclogcleaner.mclogcleaner_text_enabled', 'true')),
        ];
    }

    public function saveSettings(array $data): void
    {
        $this->writeToEnvironment([
            'MCLOGCLEANER_TEXT_ENABLED' => $data['mclogcleaner_text_enabled'],
        ]);

        Notification::make()
            ->title('McLogCleaner')
            ->body('Settings saved!')
            ->success()
            ->send();
    }
}
