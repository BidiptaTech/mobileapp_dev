<?php

namespace App\Helpers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Http\Controllers\NotificationController;

class NotificationHelper
{
    /**
     * Send notification to guest(s) by email
     *
     * @param string|array $emails - Single email or array of emails
     * @param string $title - Notification title
     * @param string $body - Notification body
     * @param string|null $image - Deprecated: Not used (kept for backward compatibility)
     * @param array $data - Optional data payload
     * @return array
     */
    public static function sendNotificationToGuest($emails, $title, $body, $image = null, $data = [])
    {
        try {
            $firebase = (new Factory)
                ->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'))
                ->withDatabaseUri('https://travhorse-96ee0-default-rtdb.asia-southeast1.firebasedatabase.app');
            
            $database = $firebase->createDatabase();
            $messaging = $firebase->createMessaging();
            // Convert single email to array
            $emailArray = is_array($emails) ? $emails : [$emails];
            $results = [];
            
            foreach ($emailArray as $email) {
                $encodedEmail = base64_encode(trim(strtolower($email)));
                $ref = $database->getReference("user_tokens/{$encodedEmail}");
                $snapshot = $ref->getSnapshot();

                if (!$snapshot->exists()) {
                    $results[] = [
                        'email' => $email,
                        'success' => false,
                        'message' => 'No devices registered for this email'
                    ];
                    continue;
                }

                // Collect tokens for this email from all devices
                $tokens = [];
                $deviceData = $snapshot->getValue();
                
                // Log the raw data structure for debugging
                \Log::info('Firebase snapshot data', [
                    'email' => $email,
                    'encoded_email' => $encodedEmail,
                    'has_data' => !empty($deviceData),
                    'data_type' => gettype($deviceData),
                    'device_count' => is_array($deviceData) ? count($deviceData) : 0
                ]);
                
                if (empty($deviceData)) {
                    $results[] = [
                        'email' => $email,
                        'success' => false,
                        'message' => 'No devices found in Firebase'
                    ];
                    continue;
                }
                
                // Iterate through all devices
                foreach ($deviceData as $deviceNodeKey => $device) {
                    // Handle both array and object formats
                    $deviceArray = is_array($device) ? $device : (array) $device;
                    
                    if (!empty($deviceArray['token'])) {
                        $tokens[] = $deviceArray['token'];
                        \Log::info('Found device token', [
                            'email' => $email,
                            'device_id' => $deviceArray['device_id'] ?? $deviceNodeKey,
                            'platform' => $deviceArray['platform'] ?? 'unknown',
                            'token_length' => strlen($deviceArray['token'])
                        ]);
                    } else {
                        \Log::warning('Device missing token', [
                            'email' => $email,
                            'device_key' => $deviceNodeKey,
                            'device_data' => $deviceArray
                        ]);
                    }
                }

                if (empty($tokens)) {
                    $results[] = [
                        'email' => $email,
                        'success' => false,
                        'message' => 'No valid tokens found in devices'
                    ];
                    \Log::warning('No tokens extracted', [
                        'email' => $email,
                        'device_count' => count($deviceData),
                        'device_keys' => array_keys($deviceData)
                    ]);
                    continue;
                }
                
                \Log::info('Tokens collected successfully', [
                    'email' => $email,
                    'total_tokens' => count($tokens),
                    'device_count' => count($deviceData)
                ]);

                // Convert all data values to strings (Firebase requirement)
                $stringData = [];
                if (!empty($data)) {
                    foreach ($data as $key => $value) {
                        if (is_array($value) || is_object($value)) {
                            $stringData[$key] = json_encode($value);
                        } elseif (is_bool($value)) {
                            $stringData[$key] = $value ? '1' : '0';
                        } elseif (is_null($value)) {
                            $stringData[$key] = '';
                        } else {
                            $stringData[$key] = (string) $value;
                        }
                    }
                }

                // Build Notification & Message (no image to avoid display issues)
                $notification = Notification::create($title, $body, null);
                $message = CloudMessage::new()->withNotification($notification);
                
                // Attach data payload if provided (all values must be strings)
                if (!empty($stringData)) {
                    $message = $message->withData($stringData);
                }
                
                // Log for debugging
                \Log::info('Preparing to send notification to multiple devices', [
                    'email' => $email,
                    'token_count' => count($tokens),
                    'device_count' => count($deviceData),
                    'data_keys' => array_keys($stringData),
                    'tokens_preview' => array_map(function($token) {
                        return substr($token, 0, 20) . '...';
                    }, array_slice($tokens, 0, 3)) // Show first 3 tokens (truncated)
                ]);
                
                // Send multicast (Firebase supports up to 500 tokens per call)
                // Split into batches if more than 500 tokens
                $batchSize = 500;
                $totalSuccess = 0;
                $totalFailure = 0;
                $allFailedIndices = [];
                
                $tokenBatches = array_chunk($tokens, $batchSize);
                
                foreach ($tokenBatches as $batchIndex => $tokenBatch) {
                    try {
                        \Log::info('Sending batch', [
                            'email' => $email,
                            'batch_index' => $batchIndex + 1,
                            'total_batches' => count($tokenBatches),
                            'tokens_in_batch' => count($tokenBatch)
                        ]);
                        
                        $report = $messaging->sendMulticast($message, $tokenBatch);
                        $batchSuccess = $report->successes()->count();
                        $batchFailure = $report->failures()->count();
                        $totalSuccess += $batchSuccess;
                        $totalFailure += $batchFailure;
                        
                        \Log::info('Batch sent', [
                            'email' => $email,
                            'batch_index' => $batchIndex + 1,
                            'success_count' => $batchSuccess,
                            'failure_count' => $batchFailure
                        ]);
                        
                        // Process failures for this batch
                        $failures = $report->failures();
                        foreach ($failures as $index => $failure) {
                            $error = $failure->error();
                            $actualIndex = ($batchIndex * $batchSize) + $index;
                            $allFailedIndices[] = [
                                'index' => $actualIndex, 
                                'error' => $error ? $error->getMessage() : 'unknown'
                            ];
                            
                            // Remove token if NotRegistered or InvalidArgument
                            $errorMsg = $error ? $error->getMessage() : '';
                            if (stripos($errorMsg, 'NotRegistered') !== false || stripos($errorMsg, 'InvalidRegistration') !== false) {
                                $tokenToRemove = $tokenBatch[$index];
                                $children = $ref->getSnapshot()->getValue();
                                if ($children) {
                                    foreach ($children as $childKey => $childVal) {
                                        if (isset($childVal['token']) && $childVal['token'] === $tokenToRemove) {
                                            $database->getReference("user_tokens/{$encodedEmail}/{$childKey}")->remove();
                                            \Log::info('Removed invalid token', ['email' => $email, 'device_key' => $childKey]);
                                        }
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        \Log::error('Error sending batch', [
                            'email' => $email,
                            'batch_index' => $batchIndex,
                            'error' => $e->getMessage()
                        ]);
                        $totalFailure += count($tokenBatch);
                    }
                }
                
                \Log::info('All batches processed', [
                    'email' => $email,
                    'total_tokens' => count($tokens),
                    'total_success' => $totalSuccess,
                    'total_failure' => $totalFailure,
                    'total_batches' => count($tokenBatches)
                ]);
                
                $results[] = [
                    'email' => $email,
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'successCount' => $totalSuccess,
                    'failureCount' => $totalFailure,
                    'totalTokens' => count($tokens),
                    'totalDevices' => count($deviceData),
                    'failed' => $allFailedIndices
                ];
            }
            
            return [
                'success' => true,
                'message' => 'Notifications processed',
                'results' => $results
            ];
            
        } catch (\Throwable $e) {
            \Log::error('Notification Helper Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to send notifications',
                'error' => $e->getMessage(),
                'results' => []
            ];
        }
    }
}
