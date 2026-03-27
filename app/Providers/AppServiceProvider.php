<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

use Illuminate\Support\Facades\Storage;
use Google\Client as GoogleClient;
use Masbug\Flysystem\GoogleDriveAdapter;
use League\Flysystem\Filesystem;

use Illuminate\Support\Facades\Log;

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
                try {
                    $client = new GoogleClient();
                    
                    // Validar presencia de llaves críticas
                    if (empty($config['client_id']) || empty($config['client_secret']) || empty($config['refresh_token'])) {
                        Log::error('Google Drive Config Error: Missing credentials in filesystems.php');
                        throw new \Exception('Missing Google Drive credentials.');
                    }

                    $client->setClientId($config['client_id']);
                    $client->setClientSecret($config['client_secret']);
                    
                    $tokens = $client->fetchAccessTokenWithRefreshToken($config['refresh_token']);
                    
                    if (isset($tokens['error'])) {
                        Log::error('Google Drive Auth Error: ' . ($tokens['error_description'] ?? $tokens['error']));
                        throw new \Exception('Google Auth Failed: ' . ($tokens['error_description'] ?? $tokens['error']));
                    }
                    
                    $client->setAccessToken($tokens);

                    $service = new \Google\Service\Drive($client);
                    $adapter = new GoogleDriveAdapter($service, $config['folder_id'] ?? '/');
                    $filesystem = new Filesystem($adapter);

                    return new \Illuminate\Filesystem\FilesystemAdapter($filesystem, $adapter, $config);
                } catch (\Exception $e) {
                    Log::error('In-Storage-Extend Google Drive Error: ' . $e->getMessage());
                    throw $e;
                }
            });
        } catch (\Exception $e) {
            Log::error('General AppServiceProvider Storage::extend Error: ' . $e->getMessage());
        }
    }
}
