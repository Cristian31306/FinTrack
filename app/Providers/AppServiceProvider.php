<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Storage;
use Google\Client as GoogleClient;
use Masbug\Flysystem\GoogleDriveAdapter;
use League\Flysystem\Filesystem;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        try {
            Storage::extend('google_drive', function ($app, $config) {
                $client = new GoogleClient();
                $client->setClientId($config['clientId']);
                $client->setClientSecret($config['clientSecret']);
                $client->setAccessToken($client->fetchAccessTokenWithRefreshToken($config['refreshToken']));

                $service = new \Google\Service\Drive($client);
                $adapter = new GoogleDriveAdapter($service, $config['folderId'] ?? '/');
                $filesystem = new Filesystem($adapter);

                return new \Illuminate\Filesystem\FilesystemAdapter($filesystem, $adapter, $config);
            });
        } catch (\Exception $e) {
            // Silently fail if not configured
        }
    }
}
