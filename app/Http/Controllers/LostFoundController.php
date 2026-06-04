<?php

namespace App\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Models\LostFound;
use App\Models\Tour;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;

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
        ]);

        $guestImageUrls = $this->processGuestImageUploads($request);

        if ($guestImageUrls instanceof \Illuminate\Http\JsonResponse) {
            return $guestImageUrls;
        }

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
            $payload = $validated;
            if ($guestImageUrls !== []) {
                $payload['guest_images'] = $this->normalizeGuestImageUrls($guestImageUrls);
            }

            $lostFound = LostFound::create($payload);
            $storedGuestImages = $this->normalizeGuestImageUrls(
                $this->decodeJsonColumn($lostFound->guest_images) ?? []
            );

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
                    'guest_images' => $storedGuestImages,
                    'images_uploaded' => count($storedGuestImages),
                    'resolved' => (bool) $lostFound->resolved,
                ],
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Lost and found store error: ' . $e->getMessage(), [
                'tour_id' => $validated['tour_id'] ?? null,
                'dmc_id' => $validated['dmc_id'] ?? null,
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
            'comments' => $this->latestResolveComment($item->comments),
            'images' => $this->decodeJsonColumn($item->images),
            'guest_images' => $this->normalizeGuestImageUrls(
                $this->decodeJsonColumn($item->guest_images) ?? []
            ),
            'resolved' => (bool) $item->resolved,
        ];
    }

    /**
     * Latest resolve comment only (last item in stored history array).
     *
     * @param  mixed  $comments
     * @return array<string, mixed>|null
     */
    private function latestResolveComment($comments): ?array
    {
        $history = $this->normalizeResolveCommentsHistory($comments);

        if ($history === []) {
            return null;
        }

        $latest = end($history);

        return is_array($latest) ? $latest : null;
    }

    /**
     * Payload is id + resolve/success + user + comments — not a new report.
     * time_date is not accepted from the client; it is added server-side when saving comments JSON.
     */
    private function isResolveLostFoundPayload(Request $request): bool
    {
        $hasResolveFlag = $request->exists('success') || $request->exists('resolve');

        if (!$request->filled('id') || !$hasResolveFlag) {
            return false;
        }

        return !$request->filled('tour_id')
            && !$request->filled('subject')
            && !$request->filled('email');
    }

    private function resolveLostFound(Request $request)
    {
        $resolveValue = $request->input('success', $request->input('resolve'));
        $parsedResolve = $this->parseResolveBoolean($resolveValue);

        if ($parsedResolve === null) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid resolve value. Use yes/no, true/false, or 1/0.',
            ], 422);
        }

        $request->merge(['success' => $parsedResolve]);

        $validated = $request->validate([
            'id' => ['required', 'integer', 'exists:lost_found,id'],
            'success' => ['required', 'boolean'],
            'user' => ['required', 'string', 'max:255'],
            'comments' => ['required', 'string'],
        ]);

        try {
            $lostFound = LostFound::query()->findOrFail($validated['id']);
            $lostFound->resolved = $validated['success'];
            $lostFound->comments = $this->appendResolveCommentEntry(
                $lostFound->comments,
                $validated['success'],
                $validated['user'],
                $validated['comments']
            );
            $lostFound->save();

            $storedComments = $this->decodeJsonColumn($lostFound->comments);

            return response()->json([
                'success' => true,
                'message' => 'Lost and found record updated successfully.',
                'data' => [
                    'id' => (int) $lostFound->id,
                    'resolved' => (bool) $lostFound->resolved,
                    'comments' => $storedComments,
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
     * Append a resolve comment object; keeps prior entries (migrates legacy single-object JSON).
     *
     * @param  mixed  $existingComments
     * @return array<int, array{resolve: bool, user: string, comments: string, time_date: string}>
     */
    private function appendResolveCommentEntry(
        $existingComments,
        bool $resolve,
        string $user,
        string $comments
    ): array {
        $history = $this->normalizeResolveCommentsHistory($existingComments);

        $history[] = [
            'resolve' => $resolve,
            'user' => $user,
            'comments' => $comments,
            'time_date' => now()->format('Y-m-d H:i:s'),
        ];

        return $history;
    }

    /**
     * @param  mixed  $existingComments
     * @return array<int, array<string, mixed>>
     */
    private function normalizeResolveCommentsHistory($existingComments): array
    {
        $decoded = $this->decodeJsonColumn($existingComments);

        if ($decoded === null || $decoded === '') {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded)) {
            return array_values($decoded);
        }

        if (isset($decoded['resolve']) || isset($decoded['user']) || isset($decoded['comments'])) {
            return [$decoded];
        }

        return [];
    }

    /**
     * Accept yes/no, true/false, 1/0 (form-data often sends "yes" / "no" strings).
     */
    private function parseResolveBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && in_array($value, [0, 1], true)) {
            return (bool) $value;
        }

        $normalized = strtolower(trim((string) $value));

        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return null;
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

    /**
     * Validate multipart guest_images[] files, upload via CommonHelper::image_path (Azure/S3/local),
     * return list of public URLs or a JsonResponse on validation / upload failure.
     *
     * @return array<int, string>|\Illuminate\Http\JsonResponse
     */
    private function processGuestImageUploads(Request $request)
    {
        $files = $this->collectGuestImageFiles($request);

        if ($files === []) {
            return [];
        }

        $validator = Validator::make(
            ['guest_images' => $files],
            [
                'guest_images' => ['array', 'max:30'],
                'guest_images.*' => [
                    'required',
                    'file',
                    'max:16384',
                    function (string $attribute, $file, \Closure $fail): void {
                        if (!$file instanceof UploadedFile || !$file->isValid()) {
                            $fail('Invalid file upload.');

                            return;
                        }
                        $allowed = ['jpg', 'jpeg', 'webp', 'heic', 'heif', 'png', 'tif', 'tiff', 'svg', 'dng', 'bmp', 'gif'];
                        $ext = strtolower((string) $file->getClientOriginalExtension());
                        if (!in_array($ext, $allowed, true)) {
                            $fail('Allowed image types: ' . implode(', ', $allowed) . '.');
                        }
                    },
                ],
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid guest_images.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $urls = [];
        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            // uploads container + logo_{time}_{random}.ext (same as other app uploads)
            $result = CommonHelper::image_path('file_storage', $file, 'uploads');
            $url = $result['master_value'] ?? null;
            if (is_string($url) && $url !== '') {
                $urls[] = $url;
            }
        }

        if ($urls === [] && $files !== []) {
            return response()->json([
                'success' => false,
                'message' => 'Image upload failed or storage is not configured. Check file_storage setting and Azure/S3/local config.',
            ], 422);
        }

        return $urls;
    }

    /**
     * Flat JSON list of image URLs: ["https://.../uploads/logo_x.jpg", "https://.../logo_y.jpg"]
     *
     * @param  mixed  $urls
     * @return array<int, string>
     */
    private function normalizeGuestImageUrls($urls): array
    {
        if (!is_array($urls)) {
            return is_string($urls) && $urls !== '' ? [$urls] : [];
        }

        $flat = [];
        array_walk_recursive($urls, function ($value) use (&$flat): void {
            if (is_string($value) && trim($value) !== '') {
                $flat[] = trim($value);
            }
        });

        return array_values(array_unique($flat));
    }

    /**
     * Collect every guest_images file (guest_images[], guest_images[0], Postman multi-select, etc.).
     *
     * @return array<int, UploadedFile>
     */
    private function collectGuestImageFiles(Request $request): array
    {
        $files = [];
        $seen = [];

        foreach (['guest_images', 'guest_images[]'] as $field) {
            if (!$request->hasFile($field)) {
                continue;
            }

            $uploaded = $request->file($field);
            if (is_array($uploaded)) {
                foreach ($uploaded as $file) {
                    $this->pushGuestImageFile($file, $files, $seen);
                }
            } else {
                $this->pushGuestImageFile($uploaded, $files, $seen);
            }
        }

        // Fallback only when Laravel did not parse the multipart files (unusual clients)
        if ($files === []) {
            foreach ($_FILES as $fieldName => $fileBag) {
                if (!is_string($fieldName) || !preg_match('/guest_images/i', $fieldName)) {
                    continue;
                }

                if (is_array($fileBag)) {
                    $this->appendFilesFromPhpFilesBag($fileBag, $files, $seen);
                }
            }
        }

        return $files;
    }

    /**
     * Build UploadedFile instances from raw $_FILES structure (multi-file array uploads).
     *
     * @param  array<string, mixed>  $fileBag
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, true>  $seen
     */
    private function appendFilesFromPhpFilesBag(array $fileBag, array &$files, array &$seen): void
    {
        if (!isset($fileBag['name'])) {
            return;
        }

        if (!is_array($fileBag['name'])) {
            if (($fileBag['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                && is_string($fileBag['tmp_name'] ?? null)
                && is_uploaded_file($fileBag['tmp_name'])) {
                $symfonyFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
                    $fileBag['tmp_name'],
                    (string) $fileBag['name'],
                    $fileBag['type'] ?? null,
                    (int) $fileBag['error'],
                    true
                );
                $this->pushGuestImageFile(UploadedFile::createFromBase($symfonyFile), $files, $seen);
            }

            return;
        }

        foreach (array_keys($fileBag['name']) as $index) {
            if (($fileBag['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }

            $tmpName = $fileBag['tmp_name'][$index] ?? null;
            if (!is_string($tmpName) || !is_uploaded_file($tmpName)) {
                continue;
            }

            $symfonyFile = new \Symfony\Component\HttpFoundation\File\UploadedFile(
                $tmpName,
                (string) $fileBag['name'][$index],
                $fileBag['type'][$index] ?? null,
                (int) ($fileBag['error'][$index] ?? UPLOAD_ERR_OK),
                true
            );
            $this->pushGuestImageFile(UploadedFile::createFromBase($symfonyFile), $files, $seen);
        }
    }

    /**
     * @param  mixed  $file
     * @param  array<int, UploadedFile>  $files
     * @param  array<string, true>  $seen  temp paths already queued
     */
    private function pushGuestImageFile($file, array &$files, array &$seen): void
    {
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return;
        }

        $path = $file->getRealPath();
        $key = ($path !== false && $path !== '') ? $path : spl_object_hash($file);

        if (isset($seen[$key])) {
            return;
        }

        $seen[$key] = true;
        $files[] = $file;
    }
}
