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
     * @param string|null $image - Optional image URL
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

                // Collect tokens for this email
                $tokens = [];
                foreach ($snapshot->getValue() as $deviceNodeKey => $device) {
                    if (!empty($device['token'])) {
                        $tokens[] = $device['token'];
                    }
                }

                if (empty($tokens)) {
                    $results[] = [
                        'email' => $email,
                        'success' => false,
                        'message' => 'No valid tokens found'
                    ];
                    continue;
                }

                // Build Notification & Message
                $notification = Notification::create($title, $body, $image ?? null);
                $message = CloudMessage::new()->withNotification($notification);
                
                // Attach data payload if provided
                if (!empty($data)) {
                    $message = $message->withData($data);
                }
                
                // Send multicast
                $report = $messaging->sendMulticast($message, $tokens);
                
                // Process failures and remove invalid tokens
                $failedIndices = [];
                $failures = $report->failures();
                
                foreach ($failures as $index => $failure) {
                    $error = $failure->error();
                    $failedIndices[] = ['index' => $index, 'error' => $error ? $error->getMessage() : 'unknown'];
                    
                    // Remove token if NotRegistered or InvalidArgument
                    $errorMsg = $error ? $error->getMessage() : '';
                    if (stripos($errorMsg, 'NotRegistered') !== false || stripos($errorMsg, 'InvalidRegistration') !== false) {
                        $tokenToRemove = $tokens[$index];
                        $children = $ref->getSnapshot()->getValue();
                        if ($children) {
                            foreach ($children as $childKey => $childVal) {
                                if (isset($childVal['token']) && $childVal['token'] === $tokenToRemove) {
                                    $database->getReference("user_tokens/{$encodedEmail}/{$childKey}")->remove();
                                }
                            }
                        }
                    }
                }

                $results[] = [
                    'email' => $email,
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'successCount' => $report->successes()->count(),
                    'failureCount' => $report->failures()->count(),
                    'failed' => $failedIndices
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
