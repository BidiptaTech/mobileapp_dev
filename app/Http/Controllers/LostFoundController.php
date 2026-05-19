<?php

namespace App\Http\Controllers;

use App\Models\LostFound;
use App\Models\Tour;
use Illuminate\Http\Request;

class LostFoundController extends Controller
{
    public function store(Request $request)
    {
        if ($this->isResolveLostFoundPayload($request)) {
            return $this->resolveLostFound($request);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'tour_id' => ['required', 'integer'],
            'dmc_id' => ['required', 'integer'],
            'guest_images' => ['sometimes', 'nullable', 'array'],
            'guest_images.*' => ['nullable', 'string', 'max:4096'],
        ]);

        $tour = Tour::query()
            ->where('tour_id', $validated['tour_id'])
            ->first();

        if (!$tour) {
            return response()->json([
                'success' => false,
                'message' => 'Tour not found for the provided tour_id.',
            ], 404);
        }

        if ((int) $tour->dmc_id !== (int) $validated['dmc_id']) {
            return response()->json([
                'success' => false,
                'message' => 'dmc_id does not match the tour record.',
            ], 422);
        }

        try {
            $lostFound = LostFound::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Lost and found report submitted successfully.',
                'data' => [
                    'id' => $lostFound->id,
                    'tour_id' => (int) $lostFound->tour_id,
                    'dmc_id' => (int) $lostFound->dmc_id,
                    'subject' => $lostFound->subject,
                    'description' => $lostFound->description,
                    'phone' => $lostFound->phone,
                    'email' => $lostFound->email,
                    'guest_images' => $this->decodeJsonColumn($lostFound->guest_images),
                    'resolved' => (bool) $lostFound->resolved,
                ],
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Lost and found store error: ' . $e->getMessage(), [
                'tour_id' => $validated['tour_id'],
                'dmc_id' => $validated['dmc_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit lost and found report.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'tour_id' => ['required', 'integer'],
            'dmc_id' => ['required', 'integer'],
        ]);

        try {
            $records = LostFound::query()
                ->where('tour_id', $validated['tour_id'])
                ->where('dmc_id', $validated['dmc_id'])
                ->orderByDesc('id')
                ->get()
                ->map(fn (LostFound $item) => $this->formatLostFoundRecord($item))
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Lost and found records fetched successfully.',
                'data' => [
                    'tour_id' => (int) $validated['tour_id'],
                    'dmc_id' => (int) $validated['dmc_id'],
                    'count' => $records->count(),
                    'records' => $records,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Lost and found fetch error: ' . $e->getMessage(), [
                'tour_id' => $validated['tour_id'],
                'dmc_id' => $validated['dmc_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch lost and found records.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function formatLostFoundRecord(LostFound $item): array
    {
        return [
            'id' => (int) $item->id,
            'tour_id' => $item->tour_id !== null ? (int) $item->tour_id : null,
            'dmc_id' => $item->dmc_id !== null ? (int) $item->dmc_id : null,
            'subject' => $item->subject,
            'description' => $item->description,
            'phone' => $item->phone,
            'email' => $item->email,
            'comments' => $item->comments,
            'images' => $this->decodeJsonColumn($item->images),
            'guest_images' => $this->decodeJsonColumn($item->guest_images),
            'resolved' => (bool) $item->resolved,
        ];
    }

    /**
     * Payload is only id + success (mark lost_found row resolved).
     */
    private function isResolveLostFoundPayload(Request $request): bool
    {
        if (!$request->filled('id') || !$request->exists('success')) {
            return false;
        }

        return !$request->filled('tour_id')
            && !$request->filled('subject')
            && !$request->filled('email');
    }

    private function resolveLostFound(Request $request)
    {
        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:lost_found,id'],
            'success' => ['required', 'boolean'],
        ]);

        try {
            $lostFound = LostFound::query()->findOrFail($validated['id']);
            $lostFound->resolved = (bool) $validated['success'];
            $lostFound->save();

            return response()->json([
                'success' => true,
                'message' => 'Lost and found record updated successfully.',
                'data' => [
                    'id' => (int) $lostFound->id,
                    'resolved' => (bool) $lostFound->resolved,
                ],
            ]);
        } catch (\Throwable $e) {
            \Log::error('Lost and found resolve error: ' . $e->getMessage(), [
                'id' => $validated['id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update lost and found record.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @param  mixed  $value
     * @return mixed
     */
    private function decodeJsonColumn($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }
}
