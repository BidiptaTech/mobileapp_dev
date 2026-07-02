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
use Illuminate\Validation\Rule;
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
            'dmc_email' => [
                Rule::requiredIf(fn () => strtolower((string) $request->input('type')) === 'dmc'),
                'nullable',
                'email',
                'max:255',
            ],
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

            if (in_array($type, ['agent', 'dmc'], true)) {
                $emailToStore = $type === 'agent'
                    ? $this->resolveAgentEmailForChatroom($userId)
                    : (string) ($validated['dmc_email'] ?? '');

                if ($emailToStore === '') {
                    return response()->json([
                        'success' => false,
                        'message' => $type === 'agent'
                            ? 'Agent email not found for the provided id.'
                            : 'dmc_email is required and must be a valid email for type dmc.',
                    ], 422);
                }

                $this->appendEmailToChatroomEmails($database, $tourId, $emailToStore);
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
            if (!$user instanceof User || (int) $user->dmcId !== $requestedId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authenticated user does not match the provided dmc_id.',
                ], 403);
            }
        }

        try {
            $requestedDate = $request->filled('date')
                ? date('Y-m-d', strtotime((string) $validated['date']))
                : null;
            $datesToCheck = $request->filled('date')
                ? [$requestedDate]
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
                    ->whereIn('tour_status', ['Confirmed', 'Definite', 'Actual']);

                if ($requestedDate !== null) {
                    // If date is provided for agent/dmc: match only check_in_time.
                    $toursQuery->whereDate('check_in_time', $requestedDate);
                } else {
                    // Default for agent/dmc: include tours active on today or tomorrow.
                    $toursQuery->where(function ($outerQuery) use ($datesToCheck) {
                        foreach ($datesToCheck as $dateToCheck) {
                            $outerQuery->orWhere(function ($activeOnDateQuery) use ($dateToCheck) {
                                $activeOnDateQuery->whereDate('check_in_time', '<=', $dateToCheck)
                                    ->where(function ($checkOutQuery) use ($dateToCheck) {
                                        $checkOutQuery->whereDate('check_out_time', '>=', $dateToCheck)
                                            ->orWhere(function ($nullCheckOutQuery) use ($dateToCheck) {
                                                $nullCheckOutQuery->whereNull('check_out_time')
                                                    ->whereDate('check_in_time', $dateToCheck);
                                            });
                                    });
                            });
                        }
                    });
                }

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

                $chatrooms = $this->collectAgentDmcChatrooms($tours);

                return $this->chatroomsJsonResponse($role, $requestedId, $datesToCheck, $chatrooms);
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

            $chatrooms = $this->collectDriverGuideChatrooms($jobsheets);

            return $this->chatroomsJsonResponse($role, $requestedId, $datesToCheck, $chatrooms);
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

    private function chatroomsJsonResponse(string $role, int $requestedId, array $datesToCheck, array $chatrooms)
    {
        return response()->json([
            'success' => true,
            'message' => empty($chatrooms) ? 'No Chatrooms for You' : 'Chatrooms fetched successfully.',
            'data' => [
                'type' => $role,
                'id' => $requestedId,
                'dates_checked' => $datesToCheck,
                'chatrooms' => $chatrooms,
            ],
        ], 200);
    }

    private function collectAgentDmcChatrooms($tours): array
    {
        $database = $this->createFirebaseDatabase();

        $chatrooms = [];

        foreach ($tours as $tour) {
            $chatSnapshot = $database->getReference('chat/' . $tour->tour_id)->getSnapshot();

            if (!$chatSnapshot->exists()) {
                continue;
            }

            try {
                $chatrooms[] = $this->buildTourChatroomPayload($tour);
            } catch (\Throwable $e) {
                \Log::warning('Failed to build agent/dmc chatroom payload for tour ' . $tour->tour_id . ': ' . $e->getMessage());
            }
        }

        return $chatrooms;
    }

    private function collectDriverGuideChatrooms($jobsheets): array
    {
        $database = $this->createFirebaseDatabase();

        $chatrooms = [];

        foreach ($jobsheets as $jobsheet) {
            $chatSnapshot = $database->getReference('chat/' . $jobsheet->tour_id)->getSnapshot();

            if (!$chatSnapshot->exists()) {
                continue;
            }

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

        return $chatrooms;
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
            'dmc' => $user instanceof User && (int) $user->dmcId === $id,
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

    private function resolveAgentEmailForChatroom(int $agentId): string
    {
        $agent = Agent::query()
            ->where('agent_id', $agentId)
            ->first();

        if (!$agent) {
            return '';
        }

        $email = $agent->email ?? null;

        return is_string($email) ? trim($email) : '';
    }

    private function appendEmailToChatroomEmails(Database $database, int $tourId, string $email): void
    {
        $email = trim($email);
        if ($email === '') {
            return;
        }

        $ref = $database->getReference('chat/' . $tourId . '/emails');
        $raw = $ref->getSnapshot()->getValue();
        $list = [];

        if (is_array($raw)) {
            foreach ($raw as $v) {
                if (is_string($v)) {
                    $v = trim($v);
                    if ($v !== '') {
                        $list[] = $v;
                    }
                }
            }
        }

        $normalizedNew = strtolower($email);
        foreach ($list as $existing) {
            if (strtolower($existing) === $normalizedNew) {
                return;
            }
        }

        $list[] = $email;
        $ref->set($list);
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

        $formatDate = function ($value): ?string {
            if ($value === null) {
                return null;
            }

            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }

            $timestamp = strtotime((string) $value);

            return $timestamp !== false ? date('Y-m-d', $timestamp) : null;
        };

        return [
            'tour_id' => (int) $tour->tour_id,
            'chatroom_id' => (int) $tour->tour_id,
            'date' => $formatDate($checkIn),
            'destination' => $tour->destination,
            'adult' => $tour->adult,
            'child' => $tour->child,
            'infant' => $tour->infant,
            'check_in_time' => $formatDate($checkIn),
            'check_out_time' => $formatDate($checkOut),
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
