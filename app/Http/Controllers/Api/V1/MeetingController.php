<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Meeting\RecordMeetingsRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Meeting;
use App\Services\MeetingService;
use Dedoc\Scramble\Attributes\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeetingController extends Controller
{
    private const RELATIONS = ['userA.profile', 'userA.motorcycle', 'userB.profile', 'userB.motorcycle'];

    public function __construct(private readonly MeetingService $meetings) {}

    /**
     * Report riders detected over BLE.
     *
     * Takes a batch, because passing a group means several detections at once. Each
     * entry is answered separately: one being turned down for cooldown says nothing
     * about the others.
     *
     * A single report is enough to record the encounter for both riders. Waiting for
     * the other phone to agree would drop every iOS-Android pair, since a backgrounded
     * iPhone is invisible to Android and only one direction ever detects anything.
     *
     * Every status other than a network failure means the detection can be dropped from
     * the sending queue. Only 429 and 5xx are worth retrying.
     */
    #[Response(
        status: 200,
        description: 'One outcome per detection, in the order they were sent. '
            .'status: created, cooldown, duplicate, unknown_token, expired_token, self, incognito, too_old, invalid_time. '
            .'meeting carries the card of the rider met, and is null whenever there is no meeting to point at.',
        type: 'array{success: bool, message: string, data: array{results: list<array{'
            .'event_id: string, status: string, meeting: array{'
            .'id: int, detected_at: string, latitude: float, longitude: float, user: array<string, mixed>'
            .'}|null}>}}',
    )]
    public function store(RecordMeetingsRequest $request): JsonResponse
    {
        $user = $request->user();

        $results = $this->meetings->record(
            $user,
            $request->validated()['detections'],
            $this->meetings->reportingPlatform($user, $user->currentAccessToken()?->id),
        );

        return ApiResponse::success(__('api.meetings_processed'), ['results' => $results]);
    }

    /**
     * The signed-in user's own meeting history, newest first.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $meetings = $user->meetings()
            ->with(self::RELATIONS)
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

        // Somebody else's meeting answers 404 rather than 403, so the endpoint cannot
        // be used to probe which ids exist.
        if ($meeting->user_a_id !== $user->id && $meeting->user_b_id !== $user->id) {
            return ApiResponse::error('NOT_FOUND', __('api.not_found'), null, 404);
        }

        $meeting->load(self::RELATIONS);

        return ApiResponse::success(__('api.fetched'), $meeting->card($user));
    }
}
