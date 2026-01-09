<?php

namespace Olivermbs\Enumshare;

use Illuminate\Support\ServiceProvider;
use Olivermbs\Enumshare\Commands\EnumsExportCommand;
use Olivermbs\Enumshare\Support\EnumAutoDiscovery;
use Olivermbs\Enumshare\Support\EnumExporter;
use Olivermbs\Enumshare\Support\EnumRegistry;
use Olivermbs\Enumshare\Support\EnumValidator;
use Olivermbs\Enumshare\Support\TypeScriptEnumGenerator;

class EnumshareServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/enumshare.php', 'enumshare'
        );

        $this->app->singleton(EnumValidator::class);

        $this->app->singleton(EnumAutoDiscovery::class, function ($app) {
            return new EnumAutoDiscovery(
                config('enumshare.auto_paths', []),
                $app->make(EnumValidator::class)
            );
        });

        $this->app->singleton(EnumRegistry::class, function ($app) {
            return new EnumRegistry(
                config('enumshare.enums', []),
                $app->make(EnumAutoDiscovery::class),
                $app->make(EnumValidator::class)
            );
        });

        $this->app->singleton(TypeScriptEnumGenerator::class, function ($app) {
            return new TypeScriptEnumGenerator();
        });

        $this->app->singleton(EnumExporter::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/enumshare.php' => config_path('enumshare.php'),
            ], 'enumshare-config');

            $this->commands([
                EnumsExportCommand::class,
            ]);
        }
    }
}
