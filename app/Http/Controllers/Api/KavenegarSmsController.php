<?php

namespace App\Http\Controllers\Api;

use Laravel\Pennant\Feature;
use App\Http\Controllers\Controller;
use App\Services\KavenegarMessageService;
use App\Services\KavenegarSmsDispatcher;
use App\Support\InvalidPublishTargets;
use App\Support\MessagePublicationConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Machine-client Kavenegar intake. Never accepts a provider API key,
 * arbitrary provider URI or sender override in an outbound request.
 */
final class KavenegarSmsController extends Controller
{
    public function single(Request $request, KavenegarMessageService $service, KavenegarSmsDispatcher $dispatcher): JsonResponse
    {
        if ($denied = $this->authorizeSend($request)) {
            return $denied;
        }

        $data = $request->validate([
            'receptor' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:2048'],
        ]);

        return $this->publish($request, $service, $dispatcher, 'single', [
            ['receptor' => $data['receptor'], 'message' => $data['message']],
        ]);
    }

    public function bulk(Request $request, KavenegarMessageService $service, KavenegarSmsDispatcher $dispatcher): JsonResponse
    {
        if ($denied = $this->authorizeSend($request)) {
            return $denied;
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:25'],
            'items.*.receptor' => ['required', 'string', 'max:20'],
            'items.*.message' => ['required', 'string', 'max:2048'],
        ]);

        return $this->publish($request, $service, $dispatcher, 'bulk', $data['items']);
    }

    public function otp(Request $request, KavenegarMessageService $service, KavenegarSmsDispatcher $dispatcher): JsonResponse
    {
        if ($denied = $this->authorizeSend($request)) {
            return $denied;
        }

        $data = $request->validate([
            'receptor' => ['required', 'string', 'max:20'],
            'template' => ['required', 'string', 'regex:/\A[A-Za-z][A-Za-z0-9_-]{0,63}\z/D'],
            'token' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{4,32}\z/D'],
            'token2' => ['sometimes', 'string', 'regex:/\A[A-Za-z0-9_-]{1,32}\z/D'],
            'token3' => ['sometimes', 'string', 'regex:/\A[A-Za-z0-9_-]{1,32}\z/D'],
        ]);

        return $this->publish($request, $service, $dispatcher, 'otp', [
            ['receptor' => $data['receptor']],
        ], $data);
    }

    public function status(Request $request, string $message): JsonResponse
    {
        if (! in_array('messages.read', (array) $request->attributes->get('relay.scopes', []), true)) {
            return $this->problem(403, 'Forbidden', 'Message status scope is required.');
        }

        if (! preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $message)) {
            return $this->problem(404, 'Not found', 'Message was not found.');
        }

        $client = (int) $request->attributes->get('relay.client_id');
        $row = DB::table('messages')->where('id', $message)
            ->where('client_id', $client)->first(['id', 'created_at']);
        if (! $row) {
            return $this->problem(404, 'Not found', 'Message was not found.');
        }

        $deliveries = DB::table('deliveries as deliveries')
            ->leftJoin('delivery_attempts as attempts', 'attempts.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.client_id', $client)
            ->where('deliveries.message_id', $message)
            ->orderBy('deliveries.id')
            ->get(['deliveries.id', 'deliveries.state', 'deliveries.attempt_count',
                'deliveries.last_error_code', 'attempts.provider_ref',
                'attempts.http_status', 'attempts.outcome', 'attempts.created_at as attempted_at']);

        return response()->json(['data' => [
            'message_id' => $row->id,
            'created_at' => $row->created_at,
            'deliveries' => $deliveries,
        ]], 200, ['Cache-Control' => 'no-store']);
    }

    private function authorizeSend(Request $request): ?JsonResponse
    {
        if (! Feature::globally()->active('message-publishing')
            || ! Feature::globally()->active('kavenegar-sending')) {
            return $this->problem(503, 'Sending disabled', 'Kavenegar SMS sending is not enabled.');
        }

        if (! in_array('messages.publish', (array) $request->attributes->get('relay.scopes', []), true)) {
            return $this->problem(403, 'Forbidden', 'The client key is not allowed to send SMS.');
        }

        if (strlen($request->getContent()) > 16384) {
            return $this->problem(413, 'Request too large', 'The SMS request is too large.');
        }

        $idem = $request->header('Idempotency-Key');
        if (! is_string($idem) || ! preg_match('/\A[A-Za-z0-9._-]{16,128}\z/D', $idem)) {
            return $this->problem(422, 'Invalid request', 'A valid Idempotency-Key header is required.');
        }

        return null;
    }

    /**
     * @param  list<array{receptor:string,message?:string}>  $items
     * @param  array<string, mixed>  $meta
     */
    private function publish(
        Request $request,
        KavenegarMessageService $service,
        KavenegarSmsDispatcher $dispatcher,
        string $mode,
        array $items,
        array $meta = [],
    ): JsonResponse {
        $clientId = (int) $request->attributes->get('relay.client_id');

        try {
            $stored = $service->publish(
                $clientId,
                (int) $request->attributes->get('relay.key_id'),
                (string) $request->header('Idempotency-Key'),
                $mode,
                $items,
                $meta,
            );
        } catch (MessagePublicationConflict) {
            return $this->problem(409, 'Idempotency conflict', 'This key belongs to another request.');
        } catch (InvalidPublishTargets) {
            return $this->problem(422, 'Unavailable', 'This SMS provider or recipient is not available.');
        } catch (ValidationException $e) {
            throw $e;
        }

        // A replay is never sent again, even if the prior dispatch crashed
        // midway. The caller can query delivery state separately.
        if ($stored['replayed']) {
            return response()->json(['data' => [
                'message_id' => $stored['message_id'],
                'delivery_ids' => $stored['delivery_ids'],
                'state' => 'already_recorded',
                'replayed' => true,
            ]], 200, ['Cache-Control' => 'no-store']);
        }

        $dispatched = $dispatcher->dispatch($clientId, $stored['message_id']);

        return response()->json(['data' => [
            'message_id' => $stored['message_id'],
            'delivery_ids' => $stored['delivery_ids'],
            'state' => $dispatched['state'],
            'deliveries' => $dispatched['deliveries'],
            'replayed' => false,
        ]], 202, ['Cache-Control' => 'no-store']);
    }

    private function problem(int $status, string $title, string $detail): JsonResponse
    {
        return response()->json([
            'type' => 'about:blank', 'title' => $title,
            'status' => $status, 'detail' => $detail,
        ], $status, ['Content-Type' => 'application/problem+json', 'Cache-Control' => 'no-store']);
    }
}
