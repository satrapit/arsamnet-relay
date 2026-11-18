<?php

namespace App\Http\Controllers\Api;

use Laravel\Pennant\Feature;
use App\Http\Controllers\Controller;
use App\Services\MessagePublisher;
use App\Support\InvalidPublishTargets;
use App\Support\MessagePublicationConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PublishMessageController extends Controller
{
    public function __invoke(Request $request, MessagePublisher $publisher): JsonResponse
    {
        if (! Feature::globally()->active('message-publishing')) {
            return $this->problem(503, 'Publishing disabled', 'Message publishing is not enabled.');
        }

        if (! in_array('messages.publish', (array) $request->attributes->get('relay.scopes', []), true)) {
            return $this->problem(403, 'Forbidden', 'The API key does not have permission to publish messages.');
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        if (! is_string($idempotencyKey)
            || ! preg_match('/\A[A-Za-z0-9._-]{16,128}\z/D', $idempotencyKey)) {
            return $this->problem(422, 'Invalid request', 'A valid Idempotency-Key header is required.');
        }

        if (strlen($request->getContent()) > 16384) {
            return $this->problem(413, 'Request too large', 'The message request exceeds the size limit.');
        }

        $validated = $request->validate([
            'target_ids' => ['required', 'array', 'min:1', 'max:25'],
            'target_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'payload' => ['required', 'array', 'min:1'],
            'priority' => ['sometimes', 'in:low,normal,high'],
            'ttl_seconds' => ['sometimes', 'integer', 'between:60,86400'],
        ]);

        if (array_is_list($validated['payload'])) {
            throw ValidationException::withMessages(['payload' => 'The payload must be a JSON object.']);
        }

        try {
            $result = $publisher->publish(
                (int) $request->attributes->get('relay.client_id'),
                (int) $request->attributes->get('relay.key_id'),
                $idempotencyKey,
                array_map('intval', $validated['target_ids']),
                $validated['payload'],
                (string) ($validated['priority'] ?? 'normal'),
                (int) ($validated['ttl_seconds'] ?? 3600),
            );
        } catch (MessagePublicationConflict) {
            return $this->problem(409, 'Idempotency conflict', 'This key belongs to a different request.');
        } catch (InvalidPublishTargets) {
            return $this->problem(422, 'Invalid request', 'The selected target(s) are not available.');
        }

        return response()->json([
            'data' => [
                'message_id' => $result['message_id'],
                'delivery_ids' => $result['delivery_ids'],
                'state' => 'stored',
                'replayed' => $result['replayed'],
            ],
        ], $result['replayed'] ? 200 : 202, ['Cache-Control' => 'no-store']);
    }

    private function problem(int $status, string $title, string $detail): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank',
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store']);
    }
}
