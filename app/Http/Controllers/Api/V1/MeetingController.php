<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Meeting\RecordMeetingsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Meeting;
use App\Services\MeetingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    public function __construct(private readonly MeetingService $meetings) {}

    /**
     * Report riders detected over BLE.
     *
     * Takes a batch, because passing a group means several detections at once. Each
     * entry is answered separately: one being turned down for cooldown says nothing
     * about the others.
     */
    public function store(RecordMeetingsRequest $request): JsonResponse
    {
        $results = $this->meetings->record($request->user(), $request->validated()['detections']);

        return ApiResponse::success(__('api.meetings_processed'), ['results' => $results]);
    }

    /**
     * The signed-in user's own meeting history, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only meetings both phones reported. A one-sided detection never happened as
        // far as the user is concerned.
        $meetings = $user->meetings()
            ->where('confirmed', true)
            ->with('metUser.profile', 'metUser.motorcycle')
            ->orderByDesc('detected_at')
            ->paginate(perPage: min((int) $request->integer('per_page', 20), 50));

        return ApiResponse::paginated(
            __('api.fetched'),
            $meetings,
            fn (Meeting $meeting) => $meeting->card($user),
        );
    }

    public function show(Request $request, Meeting $meeting): JsonResponse
    {
        $user = $request->user();

        // Each row belongs to the phone that made the detection, and an unconfirmed
        // one is not a meeting yet. Both cases answer 404 rather than 403, so the
        // endpoint cannot be used to probe for rows that exist.
        if ($meeting->user_id !== $user->id || ! $meeting->confirmed) {
            return ApiResponse::error('NOT_FOUND', __('api.not_found'), null, 404);
        }

        $meeting->load('metUser.profile', 'metUser.motorcycle');

        return ApiResponse::success(__('api.fetched'), $meeting->card($user));
    }
}
