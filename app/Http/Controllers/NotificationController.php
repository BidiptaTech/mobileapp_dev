<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            // 'title' => 'nullable|string',
            // 'body' => 'nullable|string',
            // 'image' => 'nullable|url',
            // 'data' => 'nullable|array'
        ]);

        try {
            $firebase = (new Factory)->withServiceAccount(storage_path('app/firebase/firebase_credentials.json'));
            $database = $firebase->createDatabase();
            $messaging = $firebase->createMessaging();

            $encodedEmail = base64_encode(trim(strtolower($request->input('email'))));
            $ref = $database->getReference("user_tokens/{$encodedEmail}");
            $snapshot = $ref->getSnapshot();

            if (!$snapshot->exists()) {
                return response()->json(['message' => 'No devices registered for this email'], 404);
            }

            // collect tokens
            $tokens = [];
            foreach ($snapshot->getValue() as $deviceNodeKey => $device) {
                if (!empty($device['token'])) {
                    $tokens[] = $device['token'];
                }
            }

            if (empty($tokens)) {
                return response()->json(['message' => 'No valid tokens found'], 404);
            }
            $title = $request->input('title', 'Hello Imran');
            $body = $request->input('body', 'How are you?');
            $image = $request->input('image', 'https://www.w3schools.com/images/picture.jpg');
            // Build Notification & Message
            $notification = Notification::create($title, $body, $image ?? null);
            $message = CloudMessage::new()->withNotification($notification);

            // attach data payload if provided
            $data = $request->input('data', []);
            if (!empty($data)) {
                $message = $message->withData($data);
            }

            // send multicast
            $report = $messaging->sendMulticast($message, $tokens);

            // report contains failures: iterate and remove invalid tokens from DB
            $failedIndices = [];
            $responses = $report->responses();
            foreach ($responses as $index => $resp) {
                if (!$resp->isSuccess()) {
                    $error = $resp->error();
                    $failedIndices[] = ['index' => $index, 'error' => $error ? $error->getMessage() : 'unknown'];
                    // Remove token if NotRegistered or InvalidArgument or similar
                    $errorMsg = $error ? $error->getMessage() : '';
                    if (stripos($errorMsg, 'NotRegistered') !== false || stripos($errorMsg, 'InvalidRegistration') !== false) {
                        // find token and remove it from the database
                        $tokenToRemove = $tokens[$index];
                        // remove by searching child nodes where token == $tokenToRemove
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
            }

            return response()->json([
                'message' => 'Notification processed',
                'successCount' => $report->successes()->count(),
                'failureCount' => $report->failures()->count(),
                'failed' => $failedIndices
            ]);
        } catch (\Throwable $e) {
            \Log::error('Notification send error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
