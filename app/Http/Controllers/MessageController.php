<?php

namespace App\Http\Controllers;

use App\Models\Agent;
use App\Models\Driver;
use App\Models\Guest;
use App\Models\Guide;
use App\Models\Jobsheet;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Http\Request;
use Kreait\Firebase\Contract\Database;

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
            'type' => ['required', 'in:guest,driver,guide,agent,dmc'],
        ]);

        $user = $request->user();
        $type = $validated['type'];
        $userId = (int) $validated['id'];
        $tourId = (int) $validated['tour_id'];

        if (!$this->matchesAuthenticatedUser($user, $type, $userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated user does not match the provided type and id.',
            ], 403);
        }

        try {
            $database = $this->createFirebaseDatabase();
            $chatReference = $database->getReference('chat/' . $tourId);
            $chatSnapshot = $chatReference->getSnapshot();

            if (!$chatSnapshot->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chat room not found for the provided tour_id.',
                ], 404);
            }

            $chatRoom = $chatSnapshot->getValue() ?? [];

            if (!$this->isUserAllowedInChatRoom($chatRoom, $type, $userId, $tourId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User is not authorized to send messages in this chat room.',
                ], 403);
            }

            $messageReference = $database->getReference('chat/' . $tourId . '/Message');

            if (!$messageReference->getSnapshot()->exists()) {
                $messageReference->set([]);
            }

            return response()->json([
                'success' => true,
                'message' => 'User validated and Message node initialized successfully.',
                'data' => [
                    'tour_id' => $tourId,
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
            'driver_id' => ['nullable', 'integer'],
            'guide_id' => ['nullable', 'integer'],
            'agent_id' => ['nullable', 'integer'],
            'dmc_id' => ['nullable', 'integer'],
            'date' => ['nullable', 'date'],
        ]);

        $identifierRoles = [
            'driver_id' => 'driver',
            'guide_id' => 'guide',
            'agent_id' => 'agent',
            'dmc_id' => 'dmc',
        ];

        $filledIdentifiers = [];
        foreach ($identifierRoles as $param => $roleName) {
            if ($request->filled($param)) {
                $filledIdentifiers[$param] = $roleName;
            }
        }

        if (count($filledIdentifiers) !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Provide exactly one of: driver_id, guide_id, agent_id, dmc_id.',
            ], 422);
        }

        $paramKey = array_key_first($filledIdentifiers);
        $role = $filledIdentifiers[$paramKey];
        $requestedId = (int) $request->input($paramKey);
        $user = $request->user();

        if (in_array($role, ['driver', 'guide'], true)) {
            if (!$this->matchesAuthenticatedUser($user, $role, $requestedId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authenticated user does not match the provided ' . $role . '_id.',
                ], 403);
            }
        } elseif ($role === 'agent') {
            if (!$user instanceof Agent || (int) $user->agent_id !== $requestedId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authenticated user does not match the provided agent_id.',
                ], 403);
            }
        } else {
            if (!$user instanceof User || (int) $user->userId !== $requestedId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authenticated user does not match the provided dmc_id.',
                ], 403);
            }
        }

        try {
            $datesToCheck = $request->filled('date')
                ? [date('Y-m-d', strtotime((string) $validated['date']))]
                : [now()->toDateString(), now()->addDay()->toDateString()];

            if (in_array($role, ['agent', 'dmc'], true)) {
                $toursQuery = Tour::query()
                    ->select([
                        'tour_id',
                        'destination',
                        'adult',
                        'child',
                        'infant',
                        'check_in_time',
                        'check_out_time',
                        'male_count',
                        'female_count',
                        'child_ages',
                        'hotel',
                        'attraction',
                        'travel',
                        'restaurent',
                        'guide',
                        'port',
                        'tour_status',
                        'mainguest',
                    ])
                    ->whereIn('tour_status', ['Confirmed', 'Definite', 'Actual'])
                    ->where(function ($query) use ($datesToCheck) {
                        $query->whereIn('check_in_time', $datesToCheck)
                            ->orWhereIn('check_out_time', $datesToCheck);
                    });

                if ($role === 'agent') {
                    $toursQuery->where('agent_id', $requestedId);
                } else {
                    $toursQuery->where('dmc_id', $requestedId);
                }

                $tours = $toursQuery
                    ->orderBy('check_in_time')
                    ->get()
                    ->filter(fn ($tour) => !empty($tour->tour_id))
                    ->unique('tour_id')
                    ->values();

                $database = $this->createFirebaseDatabase();
                $chatrooms = [];

                foreach ($tours as $tour) {
                    $chatSnapshot = $database->getReference('chat/' . $tour->tour_id)->getSnapshot();

                    if ($chatSnapshot->exists()) {
                        $chatrooms[] = $this->buildTourChatroomPayload($tour);
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
            }

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

    /**
     * Use the Kreait Laravel Firebase bundle so credentials, database URL, HTTP options,
     * and auth token cache match the rest of the app (see config/firebase.php).
     */
    private function createFirebaseDatabase(): Database
    {
        return app(Database::class);
    }

    private function matchesAuthenticatedUser($user, string $type, int $id): bool
    {
        return match ($type) {
            'guest' => $user instanceof Guest && (int) $user->guest_id === $id,
            'driver' => $user instanceof Driver && (int) $user->driver_id === $id,
            'guide' => $user instanceof Guide && (int) $user->guide_id === $id,
            'agent' => $user instanceof Agent && (int) $user->agent_id === $id,
            'dmc' => $user instanceof User && (int) $user->userId === $id,
            default => false,
        };
    }

    private function isUserAllowedInChatRoom(array $chatRoom, string $type, int $id, int $tourId): bool
    {
        if (in_array($type, ['agent', 'dmc'], true)) {
            return $this->isAgentOrDmcAllowedInChatRoom($chatRoom, $type, $id, $tourId);
        }

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

    /**
     * Agent/DMC: load dmc_id from tours for tour_id; allow only if Firebase chat/{tour_id}
     * has the same dmc_id and the user is allowed for that tour (agent_id or dmc userId).
     */
    private function isAgentOrDmcAllowedInChatRoom(array $chatRoom, string $type, int $id, int $tourId): bool
    {
        if (isset($chatRoom['tour_id']) && (int) $chatRoom['tour_id'] !== $tourId) {
            return false;
        }

        $tour = Tour::query()
            ->select('tour_id', 'agent_id', 'dmc_id')
            ->where('tour_id', $tourId)
            ->first();

        if (!$tour || $tour->dmc_id === null || $tour->dmc_id === '') {
            return false;
        }

        $tourDmcId = (int) $tour->dmc_id;

        if (!isset($chatRoom['dmc_id']) || (int) $chatRoom['dmc_id'] !== $tourDmcId) {
            return false;
        }

        if ($type === 'dmc') {
            return $id === $tourDmcId;
        }

        return (int) $tour->agent_id === $id;
    }

    private function buildTourChatroomPayload(Tour $tour): array
    {
        $mainguest = $tour->mainguest;
        if (is_string($mainguest)) {
            $decoded = json_decode($mainguest, true);
            $mainguest = json_last_error() === JSON_ERROR_NONE ? $decoded : $mainguest;
        }

        $checkIn = $tour->check_in_time;
        $checkOut = $tour->check_out_time;

        return [
            'tour_id' => (int) $tour->tour_id,
            'chatroom_id' => (int) $tour->tour_id,
            'date' => $checkIn ? $checkIn->format('Y-m-d') : null,
            'destination' => $tour->destination,
            'adult' => $tour->adult,
            'child' => $tour->child,
            'infant' => $tour->infant,
            'check_in_time' => $checkIn ? $checkIn->format('Y-m-d') : null,
            'check_out_time' => $checkOut ? $checkOut->format('Y-m-d') : null,
            'male_count' => $tour->male_count,
            'female_count' => $tour->female_count,
            'child_ages' => $tour->child_ages,
            'hotel' => $tour->hotel,
            'attraction' => $tour->attraction,
            'travel' => $tour->travel,
            'restaurant' => $tour->restaurent,
            'guide' => $tour->guide,
            'port' => $tour->port,
            'tour_status' => $tour->tour_status,
            'mainguest' => $mainguest,
        ];
    }
}
