<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Guest;
use App\Models\Guide;
use App\Models\Jobsheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Kreait\Firebase\Factory;

class MessageController extends Controller
{
    public function sendMessage(Request $request)
    {
        $request->merge([
            'type' => strtolower((string) $request->input('type')),
        ]);

        $validated = $request->validate([
            'tour_id' => ['required', 'integer'],
            'id' => ['required', 'integer'],
            'type' => ['required', 'in:guest,driver,guide'],
        ]);

        $user = $request->user();

        if (!$this->matchesAuthenticatedUser($user, $validated['type'], (int) $validated['id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated user does not match the provided type and id.',
            ], 403);
        }

        try {
            $database = $this->createFirebaseDatabase();
            $chatReference = $database->getReference('chat/' . $validated['tour_id']);
            $chatSnapshot = $chatReference->getSnapshot();

            if (!$chatSnapshot->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found for the provided tour_id.',
                ], 404);
            }

            $chatRoom = $chatSnapshot->getValue() ?? [];

            if (!$this->isUserAllowedInChatRoom($chatRoom, $validated['type'], (int) $validated['id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not authorized to send messages in this chat room.',
                ], 403);
            }

            $messageReference = $database->getReference('chat/' . $validated['tour_id'] . '/Message');

            if (!$messageReference->getSnapshot()->exists()) {
                $messageReference->set([]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User validated and Message node initialized successfully.',
                'data' => [
                    'tour_id' => (int) $validated['tour_id'],
                    'message_initialized' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Chat message initialization error: ' . $e->getMessage(), [
                'tour_id' => $validated['tour_id'],
                'id' => $validated['id'],
                'type' => $validated['type'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initialize Message node.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getChatrooms(Request $request)
    {
        $validated = $request->validate([
            'driver_id' => ['nullable', 'integer', 'required_without:guide_id'],
            'guide_id' => ['nullable', 'integer', 'required_without:driver_id'],
            'date' => ['nullable', 'date'],
        ]);

        if ($request->filled('driver_id') && $request->filled('guide_id')) {
            return response()->json([
                'success' => false,
                'message' => 'Provide either driver_id or guide_id, not both.',
            ], 422);
        }

        $role = array_key_exists('driver_id', $validated) && $validated['driver_id'] !== null ? 'driver' : 'guide';
        $requestedId = (int) $validated[$role . '_id'];
        $user = $request->user();

        if (!$this->matchesAuthenticatedUser($user, $role, $requestedId)) {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated user does not match the provided ' . $role . '_id.',
            ], 403);
        }

        try {
            $datesToCheck = $request->filled('date')
                ? [date('Y-m-d', strtotime((string) $validated['date']))]
                : [now()->toDateString(), now()->addDay()->toDateString()];

            $jobsheetQuery = Jobsheet::query()
                ->select('tour_id', 'data', 'type', 'service_type', 'journey_time', 'current_status', 'date')
                ->whereIn('date', $datesToCheck)
                ->whereHas('tour', function ($query) {
                    $query->whereIn('tour_status', ['Confirmed', 'Definite', 'Actual']);
                });

            if ($role === 'driver') {
                $jobsheetQuery
                    ->whereNotNull('driver_id')
                    ->where('driver_id', $requestedId);
            } else {
                $jobsheetQuery
                    ->whereNotNull('guide_id')
                    ->where('guide_id', $requestedId);
            }

            $jobsheets = $jobsheetQuery
                ->orderBy('date')
                ->get()
                ->filter(fn ($jobsheet) => !empty($jobsheet->tour_id))
                ->unique('tour_id')
                ->values();

            $database = $this->createFirebaseDatabase();
            $chatrooms = [];

            foreach ($jobsheets as $jobsheet) {
                $chatSnapshot = $database->getReference('chat/' . $jobsheet->tour_id)->getSnapshot();

                if ($chatSnapshot->exists()) {
                    $chatrooms[] = [
                        'tour_id' => (int) $jobsheet->tour_id,
                        'chatroom_id' => (int) $jobsheet->tour_id,
                        'date' => $jobsheet->date,
                        'data' => $jobsheet->data,
                        'type' => $jobsheet->type,
                        'service_type' => $jobsheet->service_type,
                        'journey_time' => $jobsheet->journey_time,
                        'current_status' => $jobsheet->current_status,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Chatrooms fetched successfully.',
                'data' => [
                    'type' => $role,
                    'id' => $requestedId,
                    'dates_checked' => $datesToCheck,
                    'chatrooms' => $chatrooms,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Chatrooms fetch error: ' . $e->getMessage(), [
                'role' => $role,
                'requested_id' => $requestedId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch chatrooms.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function createFirebaseDatabase()
    {
        $firebase = new Factory;
        $credentialsPath = Config::get('firebase.projects.app.credentials');
        $databaseUrl = Config::get('firebase.projects.app.database.url')
            ?: 'https://travhorse-96ee0-default-rtdb.asia-southeast1.firebasedatabase.app';

        if ((!is_string($credentialsPath) || $credentialsPath === '') && !getenv('GOOGLE_APPLICATION_CREDENTIALS')) {
            throw new \RuntimeException(
                'Firebase credentials are not configured. Set FIREBASE_CREDENTIALS in .env to the full path of your service-account JSON file.'
            );
        }

        if (is_string($credentialsPath) && $credentialsPath !== '') {
            $resolvedCredentialsPath = $credentialsPath;

            if (!is_file($resolvedCredentialsPath)) {
                $resolvedCredentialsPath = base_path($credentialsPath);
            }

            if (!is_file($resolvedCredentialsPath)) {
                throw new \RuntimeException(
                    'Firebase credentials file not found. Set FIREBASE_CREDENTIALS in .env to a valid service-account JSON path.'
                );
            }

            $firebase = $firebase->withServiceAccount($resolvedCredentialsPath);
        }

        return $firebase
            ->withDatabaseUri($databaseUrl)
            ->createDatabase();
    }

    private function matchesAuthenticatedUser($user, string $type, int $id): bool
    {
        return match ($type) {
            'guest' => $user instanceof Guest && (int) $user->guest_id === $id,
            'driver' => $user instanceof Driver && (int) $user->driver_id === $id,
            'guide' => $user instanceof Guide && (int) $user->guide_id === $id,
            default => false,
        };
    }

    private function isUserAllowedInChatRoom(array $chatRoom, string $type, int $id): bool
    {
        if ($type === 'guest') {
            return isset($chatRoom['guestId']) && (int) $chatRoom['guestId'] === $id;
        }

        $chatParticipants = $chatRoom['ID'] ?? [];

        if (!is_array($chatParticipants)) {
            return false;
        }

        $firebaseKey = $type === 'driver' ? 'driverId' : 'guideId';

        foreach ($chatParticipants as $participant) {
            $participantData = is_array($participant) ? $participant : (array) $participant;

            if (isset($participantData[$firebaseKey]) && (int) $participantData[$firebaseKey] === $id) {
                return true;
            }
        }

        return false;
    }
}
