<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Guide;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Database;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FcmNotification;

class NotificationController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $database = $this->firebaseDatabase();
            $messaging = $this->firebaseMessaging();

            $encodedEmail = $this->encodeEmailForTokens($request->input('email'));
            $ref = $database->getReference('user_tokens/' . $encodedEmail);
            $snapshot = $ref->getSnapshot();

            if (!$snapshot->exists()) {
                return response()->json(['message' => 'No devices registered for this email'], 404);
            }

            $tokens = $this->extractTokensFromUserTokensNode($snapshot->getValue() ?? []);

            if ($tokens === []) {
                return response()->json(['message' => 'No valid tokens found'], 404);
            }

            $title = $request->input('title', 'Hello Imran');
            $body = $request->input('body', 'How are you?');
            $notification = FcmNotification::create($title, $body, null);
            $message = CloudMessage::new()->withNotification($notification);

            $data = $this->stringifyFcmData($request->input('data', []));
            if ($data !== []) {
                $message = $message->withData($data);
            }

            $report = $messaging->sendMulticast($message, $tokens);

            $failedIndices = [];
            foreach ($report->failures()->getItems() as $failure) {
                $error = $failure->error();
                $badToken = $failure->target()->value();
                $failedIndices[] = [
                    'token' => $badToken,
                    'error' => $error ? $error->getMessage() : 'unknown',
                ];

                $errorMsg = $error ? $error->getMessage() : '';
                if (stripos($errorMsg, 'NotRegistered') !== false || stripos($errorMsg, 'InvalidRegistration') !== false) {
                    $this->removeInvalidToken($database, $encodedEmail, $badToken);
                }
            }

            return response()->json([
                'message' => 'Notification processed',
                'successCount' => $report->successes()->count(),
                'failureCount' => $report->failures()->count(),
                'failed' => $failedIndices,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Notification send error: ' . $e->getMessage());

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Notify all chatroom participants (from emails[]) except the message sender.
     */
    public function sendChatroomNotification(Request $request)
    {
        $validated = $request->validate([
            'tour_id' => ['nullable', 'integer', 'required_without:chatroom_id'],
            'chatroom_id' => ['nullable', 'integer', 'required_without:tour_id'],
            'sender_email' => ['required', 'email', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'body' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'data' => ['sometimes', 'nullable', 'array'],
        ]);

        $chatId = (int) ($validated['tour_id'] ?? $validated['chatroom_id']);
        $senderEmail = strtolower(trim((string) $validated['sender_email']));

        try {
            $database = $this->firebaseDatabase();
            $messaging = $this->firebaseMessaging();

            $chatSnapshot = $database->getReference('chat/' . $chatId)->getSnapshot();

            if (!$chatSnapshot->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found for the provided tour_id or chatroom_id.',
                ], 404);
            }

            $chatRoom = $chatSnapshot->getValue() ?? [];
            $recipientEmails = $this->extractChatroomRecipientEmails($chatRoom, $senderEmail);
            $excludedDriverGuideEmails = [];

            if ($this->isEmergencyNotificationTitle($validated['title'] ?? null)) {
                [$recipientEmails, $excludedDriverGuideEmails] = $this->excludeDriverAndGuideEmails($recipientEmails);
            }

            if ($recipientEmails === []) {
                return response()->json([
                    'success' => false,
                    'message' => $excludedDriverGuideEmails !== []
                        ? 'No recipients remain after excluding drivers and guides for Emergency notifications.'
                        : 'No recipient emails found in the chat room (excluding sender).',
                    'data' => [
                        'chatroom_id' => $chatId,
                        'sender_email' => $senderEmail,
                        'excluded_driver_guide_emails' => $excludedDriverGuideEmails,
                    ],
                ], 404);
            }

            $devices = $this->collectDevicesForEmails($database, $recipientEmails);

            if ($devices === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No registered devices found for chatroom recipients.',
                    'data' => [
                        'chatroom_id' => $chatId,
                        'recipient_emails' => $recipientEmails,
                    ],
                ], 404);
            }

            $tokens = array_column($devices, 'token');
            $tokenToDevice = [];
            foreach ($devices as $device) {
                $tokenToDevice[$device['token']] = $device;
            }

            $title = $validated['title'] ?? 'New message';
            $body = $validated['body'] ?? 'You have a new message in the chat';
            $isEmergency = $this->isEmergencyNotificationTitle($title);
            $notification = FcmNotification::create($title, $body, null);
            $message = CloudMessage::new()->withNotification($notification);

            $data = $this->stringifyFcmData(array_merge(
                [
                    'type' => 'chat_message',
                    'tour_id' => (string) $chatId,
                    'chatroom_id' => (string) $chatId,
                ],
                $validated['data'] ?? []
            ));
            $message = $message->withData($data);

            if ($isEmergency) {
                $message = $this->applyEmergencyAlertConfig($message);
            }

            $totalSuccess = 0;
            $totalFailure = 0;
            $failures = [];

            foreach (array_chunk($tokens, 500) as $tokenBatch) {
                $report = $messaging->sendMulticast($message, $tokenBatch);
                $totalSuccess += $report->successes()->count();
                $totalFailure += $report->failures()->count();

                // failures() returns MulticastSendReport (Countable only) — iterate getItems().
                foreach ($report->failures()->getItems() as $failure) {
                    $error = $failure->error();
                    $badToken = $failure->target()->value();
                    $failures[] = [
                        'token' => $badToken,
                        'error' => $error ? $error->getMessage() : 'unknown',
                    ];

                    $errorMsg = $error ? $error->getMessage() : '';
                    if (stripos($errorMsg, 'NotRegistered') !== false || stripos($errorMsg, 'InvalidRegistration') !== false) {
                        $device = $tokenToDevice[$badToken] ?? null;
                        if ($device) {
                            $this->removeInvalidToken(
                                $database,
                                $this->encodeEmailForTokens($device['email']),
                                $badToken
                            );
                        }
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Chatroom notifications processed.',
                'data' => [
                    'chatroom_id' => $chatId,
                    'sender_email' => $senderEmail,
                    'recipient_emails' => $recipientEmails,
                    'excluded_driver_guide_emails' => $excludedDriverGuideEmails,
                    'is_emergency' => $isEmergency,
                    'devices_targeted' => count($devices),
                    'success_count' => $totalSuccess,
                    'failure_count' => $totalFailure,
                    'failures' => $failures,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Chatroom notification error: ' . $e->getMessage(), [
                'chatroom_id' => $chatId,
                'sender_email' => $senderEmail,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to send chatroom notifications.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function firebaseDatabase(): Database
    {
        return app(Database::class);
    }

    private function firebaseMessaging(): Messaging
    {
        return app(Messaging::class);
    }

    private function encodeEmailForTokens(string $email): string
    {
        return base64_encode(strtolower(trim($email)));
    }

    private function isEmergencyNotificationTitle(?string $title): bool
    {
        return strcasecmp(trim((string) $title), 'Emergency') === 0;
    }

    /**
     * For Emergency alerts: skip recipients whose email exists on drivers or guides tables.
     *
     * @param  array<int, string>  $recipientEmails
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function excludeDriverAndGuideEmails(array $recipientEmails): array
    {
        if ($recipientEmails === []) {
            return [[], []];
        }

        $normalizedRecipients = [];
        foreach ($recipientEmails as $email) {
            $normalizedRecipients[strtolower(trim($email))] = $email;
        }

        $normalizedList = array_keys($normalizedRecipients);

        $driverEmails = Driver::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(email)) IN (' . implode(',', array_fill(0, count($normalizedList), '?')) . ')', $normalizedList)
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->all();

        $guideEmails = Guide::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(email)) IN (' . implode(',', array_fill(0, count($normalizedList), '?')) . ')', $normalizedList)
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->all();

        $excludedNormalized = array_values(array_unique(array_merge($driverEmails, $guideEmails)));
        $excludedOriginal = [];

        foreach ($excludedNormalized as $normalized) {
            if (isset($normalizedRecipients[$normalized])) {
                $excludedOriginal[] = $normalizedRecipients[$normalized];
                unset($normalizedRecipients[$normalized]);
            }
        }

        return [array_values($normalizedRecipients), $excludedOriginal];
    }

    /**
     * @return array<int, string>
     */
    private function extractChatroomRecipientEmails(array $chatRoom, string $senderEmail): array
    {
        $emails = $chatRoom['emails'] ?? [];

        if (!is_array($emails)) {
            return [];
        }

        $recipients = [];

        foreach ($emails as $value) {
            if (!is_string($value)) {
                continue;
            }

            $normalized = strtolower(trim($value));

            if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            if ($normalized === $senderEmail) {
                continue;
            }

            $recipients[] = $normalized;
        }

        return array_values(array_unique($recipients));
    }

    /**
     * @param  array<int, string>  $emails
     * @return array<int, array{email: string, device_id: string, token: string, platform: string|null}>
     */
    private function collectDevicesForEmails(Database $database, array $emails): array
    {
        $devices = [];
        $seenTokens = [];

        foreach ($emails as $email) {
            $encodedEmail = $this->encodeEmailForTokens($email);
            $snapshot = $database->getReference('user_tokens/' . $encodedEmail)->getSnapshot();

            if (!$snapshot->exists()) {
                continue;
            }

            foreach ($snapshot->getValue() as $deviceKey => $device) {
                $deviceArray = is_array($device) ? $device : (array) $device;
                $token = $deviceArray['token'] ?? null;

                if (!is_string($token) || $token === '') {
                    continue;
                }

                if (isset($seenTokens[$token])) {
                    continue;
                }

                $seenTokens[$token] = true;
                $devices[] = [
                    'email' => $email,
                    'device_id' => (string) ($deviceArray['device_id'] ?? $deviceKey),
                    'token' => $token,
                    'platform' => isset($deviceArray['platform']) ? (string) $deviceArray['platform'] : null,
                ];
            }
        }

        return $devices;
    }

    /**
     * @param  mixed  $userTokensNode
     * @return array<int, string>
     */
    private function extractTokensFromUserTokensNode($userTokensNode): array
    {
        if (!is_array($userTokensNode)) {
            return [];
        }

        $tokens = [];

        foreach ($userTokensNode as $device) {
            $deviceArray = is_array($device) ? $device : (array) $device;

            if (!empty($deviceArray['token'])) {
                $tokens[] = $deviceArray['token'];
            }
        }

        return $tokens;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function stringifyFcmData(array $data): array
    {
        $stringData = [];

        foreach ($data as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $stringData[(string) $key] = json_encode($value);
            } elseif (is_bool($value)) {
                $stringData[(string) $key] = $value ? '1' : '0';
            } elseif ($value === null) {
                $stringData[(string) $key] = '';
            } else {
                $stringData[(string) $key] = (string) $value;
            }
        }

        return $stringData;
    }

    private function removeInvalidToken(Database $database, string $encodedEmail, ?string $tokenToRemove): void
    {
        if ($tokenToRemove === null || $tokenToRemove === '') {
            return;
        }

        $ref = $database->getReference('user_tokens/' . $encodedEmail);
        $children = $ref->getSnapshot()->getValue();

        if (!is_array($children)) {
            return;
        }

        foreach ($children as $childKey => $childVal) {
            $childArray = is_array($childVal) ? $childVal : (array) $childVal;

            if (isset($childArray['token']) && $childArray['token'] === $tokenToRemove) {
                $database->getReference('user_tokens/' . $encodedEmail . '/' . $childKey)->remove();
            }
        }
    }

    private function applyEmergencyAlertConfig(CloudMessage $message): CloudMessage
    {
        $androidConfig = AndroidConfig::fromArray([
            'priority' => 'high',
            'notification' => [
                'sound' => 'emergency_alert',
                'channel_id' => 'emergency_alerts',
                'color' => '#FF0000',
                'notification_priority' => 'PRIORITY_MAX',
                'default_sound' => false,
            ],
        ]);

        $apnsConfig = ApnsConfig::fromArray([
            'headers' => [
                'apns-priority' => '10',
                'apns-push-type' => 'alert',
            ],
            'payload' => [
                'aps' => [
                    'sound' => 'emergency_alert.caf',
                    'badge' => 1,
                ],
            ],
        ]);

        return $message
            ->withAndroidConfig($androidConfig)
            ->withApnsConfig($apnsConfig);
    }
}
