<?php

namespace App\Helpers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;

class CommonHelper
{
    /**
     * Upload image to configured storage (local, S3, or Azure)
     * 
     * @param string $name Setting name for storage type (e.g., 'file_storage')
     * @param \Illuminate\Http\UploadedFile $logoFile The uploaded file
     * @param string $container Container/folder name for Azure (default: 'uploads')
     * @return array Contains 'master_value' key with the image URL
     */
    public static function image_path($name, $logoFile, $container = 'uploads')
    {
        $get_filestorage = Setting::where('name', $name)->where('status', 1)->first();
        $logoName = 'logo_' . time() . '_' . Str::random(6) . '.' . $logoFile->getClientOriginalExtension();
        
        if ($get_filestorage) {
            try {
                
                if ($get_filestorage->value == 'local') {
                    $destinationPath = public_path('build/images');
                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0755, true);
                    }
                    $logoFile->move($destinationPath, $logoName);
                    $logoPath = asset('build/images/' . $logoName);
                } elseif ($get_filestorage->value == 's3') {
                    $path = Storage::disk('s3')->putFileAs($container, $logoFile, $logoName);
                    $logoPath = Storage::disk('s3')->url($path);
                } elseif ($get_filestorage->value == 'azure') {
                    // Try the direct blob client method first
                    try {
                        return self::uploadToAzure($logoFile, $logoName, $container);
                    } catch (\Exception $e) {
                        Log::warning('Direct Azure upload failed, trying Storage method', [
                            'error' => $e->getMessage()
                        ]);
                        // Fallback to Storage method
                        return self::uploadToAzureWithStorage($logoFile, $logoName, $container);
                    }
                } else {
                    $logoPath = null;
                }
               
                return [
                    'master_value' => $logoPath ?? null,
                ];
            } catch (\Exception $e) {
                Log::error("Image upload failed: " . $e->getMessage());
                return [
                    'master_value' => null,
                ];
            }
        }
        return [
            'master_value' => null,
        ];
    }

    /**
     * Upload file to Azure with dynamic container support
     * Date 16-06-2025
     * 
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @param string $fileName The filename to use
     * @param string $container Container name (default: 'uploads')
     * @return array Contains 'master_value' key with the image URL
     */
    public static function uploadToAzure($file, $fileName, $container = 'uploads')
    {
        try {
            // Get Azure configuration
            $config = config('filesystems.disks.azure');
            
            if (!$config || !isset($config['name']) || !isset($config['key'])) {
                throw new \Exception('Azure configuration is missing or incomplete');
            }
            
            // Create connection string
            $connectionString = sprintf(
                'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s;EndpointSuffix=core.windows.net',
                $config['name'],
                $config['key']
            );

            // Create blob client
            $blobClient = BlobRestProxy::createBlobService($connectionString);
            
            // Ensure container exists
            self::ensureAzureContainerExists($blobClient, $container);
            
            Log::info('Attempting Azure upload', [
                'file_name' => $fileName,
                'container' => $container
            ]);

            // Read file content
            $fileContent = file_get_contents($file->getRealPath());
            
            // Upload directly using blob client
            $blobClient->createBlockBlob($container, $fileName, $fileContent);
            
            // Generate URL
            $logoPath = sprintf(
                'https://%s.blob.core.windows.net/%s/%s',
                $config['name'],
                $container,
                $fileName
            );
            
            Log::info('Azure upload successful', [
                'path' => $fileName,
                'url' => $logoPath,
                'container' => $container
            ]);

            return [
                'master_value' => $logoPath,
            ];
        } catch (\Exception $e) {
            Log::error('Azure upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $fileName,
                'container' => $container
            ]);
            
            return [
                'master_value' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Ensure Azure container exists
     * Date 16-06-2025
     * 
     * @param BlobRestProxy $blobClient The Azure blob client
     * @param string $container Container name
     * @return void
     */
    public static function ensureAzureContainerExists($blobClient, $container)
    {
        try {
            // Try to get container properties
            $blobClient->getContainerProperties($container);
            Log::info("Container '{$container}' already exists");
        } catch (\Exception $e) {
            try {
                // Container doesn't exist, create it
                $blobClient->createContainer($container);
                Log::info("Container '{$container}' created successfully");
            } catch (\Exception $createException) {
                Log::error("Failed to create container '{$container}'", [
                    'error' => $createException->getMessage()
                ]);
                throw $createException;
            }
        }
    }

    /**
     * Upload to Azure using Laravel Storage facade (fallback method)
     * Date 16-06-2025
     * 
     * @param \Illuminate\Http\UploadedFile $file The uploaded file
     * @param string $fileName The filename to use
     * @param string $container Container name (default: 'uploads')
     * @return array Contains 'master_value' key with the image URL
     */
    public static function uploadToAzureWithStorage($file, $fileName, $container = 'uploads')
    {
        try {
            // Create a temporary Azure disk configuration for this container
            config(['filesystems.disks.azure_temp' => [
                'driver' => 'azure',
                'name' => config('filesystems.disks.azure.name'),
                'key' => config('filesystems.disks.azure.key'),
                'endpoint' => config('filesystems.disks.azure.endpoint'),
            ]]);

            // Store the file in the temporary disk
            $path = Storage::disk('azure_temp')->putFileAs($container, $file, $fileName);
            
            // Generate the URL for the stored file
            $url = Storage::disk('azure_temp')->url($path);
            
            return [
                'master_value' => $url,
            ];
        } catch (\Exception $e) {
            Log::error('Azure upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file_name' => $fileName,
                'container' => $container
            ]);
            
            return [
                'master_value' => null,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete image from Azure blob storage
     * Date 16-06-2025
     * 
     * @param string $imageUrl The full URL of the image to delete
     * @param string $container Container name (default: 'uploads')
     * @return void
     */
    public static function deleteAzureImage($imageUrl, $container = 'uploads')
    {
        try {
            $config = config('filesystems.disks.azure');
            
            if (!$config || !isset($config['name']) || !isset($config['key'])) {
                Log::warning('Azure configuration missing for image deletion');
                return;
            }
            
            $connectionString = sprintf(
                'DefaultEndpointsProtocol=https;AccountName=%s;AccountKey=%s;EndpointSuffix=core.windows.net',
                $config['name'],
                $config['key']
            );
            
            $blobClient = BlobRestProxy::createBlobService($connectionString);
            
            // Extract filename from URL
            $parsedUrl = parse_url($imageUrl);
            $pathParts = explode('/', trim($parsedUrl['path'], '/'));
            
            // Get the filename (last part of the path)
            $fileName = end($pathParts);
            
            // Extract container from URL if present, otherwise use default
            if (count($pathParts) > 1) {
                $urlContainer = $pathParts[0];
                // If the container matches a known container, use it
                $container = $urlContainer;
            }
            
            // Delete the blob
            $blobClient->deleteBlob($container, $fileName);
            
            Log::info('Azure image deleted successfully', [
                'url' => $imageUrl,
                'container' => $container,
                'file_name' => $fileName
            ]);
            
        } catch (\Exception $e) {
            // Ignore errors, just log
            Log::error('Azure image deletion failed: ' . $e->getMessage(), [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
        }
    }
}

