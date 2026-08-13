<?php

namespace App\Http\Requests\Api\V1\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class RecordMeetingsRequest extends FormRequest
{
    /**
     * Only structural problems are rejected here. Outcomes the app cannot control —
     * cooldown, an unknown token, a detection that arrived too late — come back as a
     * per-item result with a reason, so one bad entry never sinks the whole batch.
     */
    public function rules(): array
    {
        return [
            'detections' => ['required', 'array', 'min:1', 'max:'.config('motusy.meetings.max_batch_size')],
            'detections.*.event_id' => ['required', 'string', 'max:64'],
            'detections.*.ble_token' => ['required', 'string', 'size:'.(config('motusy.ble.token_bytes') * 2), 'regex:/^[0-9a-f]+$/'],
            'detections.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'detections.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'detections.*.detected_at' => ['required', 'date'],
        ];
    }
}
