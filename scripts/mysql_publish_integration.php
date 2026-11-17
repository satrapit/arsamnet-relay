<?php

/**
 * Real MySQL transaction and two-process idempotency integration checks.
 * Only works on the exact, separately migrated local integration database.
 * Does not migrate, truncate, drop, or call any message provider.
 */

use App\Enums\ProviderAttemptOutcome;
use App\Jobs\StageDeliveryJob;
use App\Services\DeliveryAttemptManager;
use App\Services\DeliveryLeaseManager;
use App\Services\FakeDispatchCoordinator;
use App\Services\FakeSignedCallbackReceipt;
use App\Services\MessagePublisher;
use App\Support\MessagePublicationConflict;
use App\Support\SchemaManifest;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @return array<string, string|bool> */
function arguments(array $argv): array
{
    $result = [];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--worker') {
            $result['worker'] = true;
        } elseif (preg_match('/\A--([a-z-]+)=(.*)\z/D', $arg, $match)) {
            if (isset($result[$match[1]])) {
                throw new RuntimeException('Repeated argument: '.$match[1]);
            }
            $result[$match[1]] = $match[2];
        } else {
            throw new RuntimeException('Unsupported argument.');
        }
    }

    return $result;
}

function ensure(bool $okay, string $detail): void
{
    if (! $okay) {
        throw new RuntimeException('CHECK FAILED: '.$detail);
    }
}

function safetyChecks(string $expected): void
{
    global $app;

    ensure($expected === 'arsamnet_relay_integration_test', 'Use the exact isolated integration schema name.');
    ensure($app->environment('local'), 'APP_ENV must be local.');
    ensure(! $app->configurationIsCached(), 'Configuration cache must be cleared.');
    ensure(DB::getDefaultConnection() === 'mysql', 'The default connection must be MySQL.');
    ensure((string) DB::selectOne('SELECT DATABASE() AS name')->name === $expected,
        'The actually selected MySQL schema does not match --expect.');

    $version = (string) DB::selectOne('SELECT VERSION() AS version')->version;
    ensure(str_starts_with($version, '8.') && ! str_contains(strtolower($version), 'mariadb'),
        'Only MySQL 8 is supported.');
    ensure((string) config('app.key') !== '', 'APP_KEY is required for encrypted test fixtures.');
    ensure(config('queue.connections.database.connection') === null
        || config('queue.connections.database.connection') === DB::getDefaultConnection(),
        'Database queue must use the selected MySQL connection.');
    ensure(config('queue.connections.database.after_commit') === false,
        'Database queue must insert inside the enclosing transaction.');
}

/** @return list<string> */
function projectTables(): array
{
    $design = SchemaManifest::parse((string) file_get_contents(dirname(__DIR__).'/docs/ERD.dbml'));

    return array_keys($design['tables']);
}

function ensureEmpty(): void
{
    foreach (projectTables() as $table) {
        ensure(DB::table($table)->count() === 0, 'Integration table is not empty: '.$table);
    }
    ensure(DB::table('migrations')->count() === 4, 'Expected four initial migrations.');
}

/** @return array{client: int, key: int, target: int} */
function fixture(): array
{
    $slug = 'mysql-test-'.bin2hex(random_bytes(7));
    $client = DB::table('clients')->insertGetId([
        'slug' => $slug, 'name' => 'Isolated integration test', 'status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $token = 'relay_'.bin2hex(random_bytes(32));
    $key = DB::table('api_keys')->insertGetId([
        'client_id' => $client,
        'label' => 'Isolated test key',
        'key_prefix' => substr($token, 0, 16),
        'key_hash' => hash('sha256', $token),
        'scopes' => json_encode(['messages.publish'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
    ]);
    $address = $slug.'@example.test';
    $target = DB::table('delivery_targets')->insertGetId([
        'client_id' => $client, 'channel' => 'email',
        'address_ciphertext' => Crypt::encryptString($address),
        'address_hmac' => hash('sha256', $address),
        'enabled' => true, 'created_at' => now(),
    ]);

    return ['client' => $client, 'key' => $key, 'target' => $target];
}

/** @return array{event: string, value: string} */
function payload(): array
{
    return ['event' => 'mysql-integration', 'value' => 'opaque-constant'];
}

/** @param array<string, string|bool> $options */
function worker(array $options): void
{
    foreach (['barrier', 'slot', 'client', 'key', 'target', 'idempotency'] as $name) {
        ensure(is_string($options[$name] ?? null) && $options[$name] !== '', 'Missing worker '.$name);
    }
    $dir = (string) $options['barrier'];
    $slot = (string) $options['slot'];
    ensure(in_array($slot, ['one', 'two'], true)
        && str_starts_with($dir, rtrim(sys_get_temp_dir(), '/').'/relay_mysql_')
        && is_dir($dir), 'Invalid worker synchronization directory.');
    foreach (['client', 'key', 'target'] as $name) {
        ensure(ctype_digit((string) $options[$name]) && (int) $options[$name] > 0,
            'Worker requires numeric fixture IDs.');
    }

    ensure(file_put_contents($dir.'/ready-'.$slot, '1', LOCK_EX) === 1,
        'Could not signal worker readiness.');
    $deadline = microtime(true) + 15;
    while (! is_file($dir.'/go')) {
        ensure(microtime(true) < $deadline, 'Worker start barrier timed out.');
        usleep(10_000);
    }

    $result = app(MessagePublisher::class)->publish(
        (int) $options['client'], (int) $options['key'],
        (string) $options['idempotency'], [(int) $options['target']], payload(),
    );
    echo json_encode(['message_id' => $result['message_id'], 'replayed' => $result['replayed']], JSON_THROW_ON_ERROR).PHP_EOL;
}

/** @param array{client: int, key: int, target: int} $f */
function runConcurrent(array $f): void
{
    $dir = rtrim(sys_get_temp_dir(), '/').'/relay_mysql_'.bin2hex(random_bytes(10));
    ensure(mkdir($dir, 0700), 'Could not create temporary synchronization directory.');
    $schema = 'arsamnet_relay_integration_test';
    $idempotency = 'concurrent-'.bin2hex(random_bytes(12));
    $processes = [];
    try {
        foreach (['one', 'two'] as $slot) {
            $command = [PHP_BINARY, __FILE__, '--worker', '--expect='.$schema,
                '--barrier='.$dir, '--slot='.$slot, '--client='.$f['client'],
                '--key='.$f['key'], '--target='.$f['target'], '--idempotency='.$idempotency];
            $process = new Process($command, dirname(__DIR__));
            $process->setTimeout(25);
            $process->start();
            $processes[$slot] = $process;
        }

        $deadline = microtime(true) + 15;
        while (! is_file($dir.'/ready-one') || ! is_file($dir.'/ready-two')) {
            ensure(microtime(true) < $deadline, 'Concurrent workers were not ready in time.');
            foreach ($processes as $process) {
                ensure($process->isRunning(), 'A concurrent worker exited before starting.');
            }
            usleep(10_000);
        }
        ensure(file_put_contents($dir.'/go', '1', LOCK_EX) === 1, 'Could not release concurrent workers.');

        $results = [];
        foreach ($processes as $slot => $process) {
            $process->wait();
            ensure($process->isSuccessful(), 'Concurrent worker '.$slot.' failed: '.$process->getErrorOutput());
            $results[] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR);
        }
        ensure(count($results) === 2, 'Expected exactly two process results.');
        ensure($results[0]['message_id'] === $results[1]['message_id'],
            'Concurrent idempotent requests returned different message IDs.');
        $replay = array_column($results, 'replayed');
        sort($replay);
        ensure($replay === [false, true], 'Exactly one request must insert and one must replay.');
        $messageId = $results[0]['message_id'];
        ensure(DB::table('messages')->where('id', $messageId)->where('client_id', $f['client'])->count() === 1,
            'Concurrent requests created an unexpected number of messages.');
        ensure(DB::table('deliveries')->where('message_id', $messageId)->count() === 1,
            'Concurrent requests created an unexpected number of deliveries.');
        echo "PASS: two simultaneous PHP processes created one message/one delivery (202 + replay semantics).\n";
    } finally {
        foreach ($processes as $process) {
            if ($process->isRunning()) {
                $process->stop(2);
            }
        }
        foreach (['go', 'ready-one', 'ready-two'] as $part) {
            if (is_file($dir.'/'.$part)) {
                unlink($dir.'/'.$part);
            }
        }
        rmdir($dir);
    }
}

/**
 * Delete only this isolated fixture and its matching queued job records.
 * Never delete other rows and never truncate or drop any table.
 */
function cleanup(?int $clientId): void
{
    if ($clientId === null) {
        return;
    }
    $deliveries = DB::table('deliveries')->where('client_id', $clientId)->pluck('id')->all();
    $jobIds = [];
    foreach (DB::table('jobs')->select('id', 'payload')->get() as $row) {
        foreach ($deliveries as $deliveryId) {
            if (str_contains((string) $row->payload, (string) $deliveryId)) {
                $jobIds[] = $row->id;
                break;
            }
        }
    }
    $attemptIds = $deliveries === [] ? [] : DB::table('delivery_attempts')
        ->whereIn('delivery_id', $deliveries)->pluck('id')->all();
    DB::transaction(function () use ($clientId, $jobIds, $deliveries, $attemptIds): void {
        if ($jobIds !== []) {
            DB::table('jobs')->whereIn('id', $jobIds)->delete();
        }
        if ($attemptIds !== []) {
            DB::table('callback_receipts')->whereIn('delivery_attempt_id', $attemptIds)->delete();
        }
        if ($deliveries !== []) {
            DB::table('delivery_attempts')->whereIn('delivery_id', $deliveries)->delete();
        }
        DB::table('deliveries')->where('client_id', $clientId)->delete();
        DB::table('messages')->where('client_id', $clientId)->delete();
        DB::table('client_provider_routes')->where('client_id', $clientId)->delete();
        DB::table('provider_accounts')->where('client_id', $clientId)->delete();
        DB::table('delivery_targets')->where('client_id', $clientId)->delete();
        DB::table('api_keys')->where('client_id', $clientId)->delete();
        DB::table('clients')->where('id', $clientId)->delete();
    });
}

try {
    $options = arguments($argv);
    $expected = (string) ($options['expect'] ?? '');
    safetyChecks($expected);

    if (! empty($options['worker'])) {
        worker($options);
        exit(0);
    }

    ensure(array_keys($options) === ['expect'], 'Parent accepts only --expect=arsamnet_relay_integration_test.');
    $verified = Artisan::call('relay:db-verify', ['--expect' => $expected]);
    ensure($verified === 0, 'Local MySQL schema verifier failed: '.Artisan::output());
    ensureEmpty();

    // Prevent two integration runs sharing this exclusive test schema.
    $lockName = 'relay_mysql_publish_integration';
    ensure((int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->acquired === 1,
        'Another integration run currently owns the schema.');
    $clientId = null;
    try {
        ensureEmpty();
        $publisher = app(MessagePublisher::class);

        DB::beginTransaction();
        try {
            $f = fixture();
            $first = $publisher->publish($f['client'], $f['key'],
                'rollback-'.bin2hex(random_bytes(10)), [$f['target']], payload());
            ensure($first['replayed'] === false && count($first['delivery_ids']) === 1,
                'Transactional publish did not insert a delivery.');
            ensure(DB::table('messages')->count() === 1 && DB::table('jobs')->count() === 1,
                'Expected same-transaction message and queue job.');
        } finally {
            DB::rollBack();
        }
        ensureEmpty();
        echo "PASS: outer MySQL rollback removed message, delivery and native DB queue job.\n";

        $f = fixture();
        $clientId = $f['client'];
        $sameKey = 'sequential-'.bin2hex(random_bytes(10));
        $first = $publisher->publish($f['client'], $f['key'], $sameKey, [$f['target']], payload());
        $again = $publisher->publish($f['client'], $f['key'], $sameKey, [$f['target']], payload());
        ensure($first['replayed'] === false && $again['replayed'] === true
            && $first['message_id'] === $again['message_id'], 'Sequential idempotency replay failed.');
        ensure(DB::table('messages')->count() === 1 && DB::table('deliveries')->count() === 1
            && DB::table('jobs')->count() === 1, 'Replay created duplicate domain or queue rows.');
        echo "PASS: MySQL sequential idempotency persisted only one message/job.\n";

        $rejected = false;
        try {
            $publisher->publish($f['client'], $f['key'], $sameKey, [$f['target']],
                ['event' => 'different-content']);
        } catch (MessagePublicationConflict) {
            $rejected = true;
        }
        ensure($rejected, 'Different content under the same idempotency key was not rejected.');
        echo "PASS: conflicting idempotency payload was rejected.\n";

        (new StageDeliveryJob($first['delivery_ids'][0]))->handle();
        ensure(DB::table('deliveries')->where('id', $first['delivery_ids'][0])
            ->where('state', 'awaiting_provider')->exists(), 'Staging state transition failed.');
        ensure(DB::table('delivery_attempts')->count() === 0, 'Provider attempt created unexpectedly.');
        echo "PASS: stage worker changed only local state; no provider attempts.\n";

        $leases = app(DeliveryLeaseManager::class);
        $deliveryId = $first['delivery_ids'][0];
        $token = $leases->claim($f['client'], $deliveryId, 30);
        ensure(is_string($token) && strlen($token) === 36, 'Failed to claim fenced delivery lease.');
        ensure($leases->claim($f['client'], $deliveryId) === null, 'Active lease was incorrectly reclaimed.');
        ensure(! $leases->renew($f['client'], $deliveryId, 'wrong-worker-token'),
            'Wrong fencing token was able to renew.');
        ensure($leases->renew($f['client'], $deliveryId, $token, 60),
            'The current fencing token could not renew its lease.');
        ensure(! $leases->quarantineExpired($f['client'], $deliveryId),
            'An unexpired lease was quarantined.');

        DB::table('deliveries')->where('client_id', $f['client'])->where('id', $deliveryId)
            ->update(['lease_expires_at' => now()->utc()->subSecond()]);
        ensure(! $leases->renew($f['client'], $deliveryId, $token),
            'An expired fencing token was allowed to renew.');
        ensure($leases->quarantineExpired($f['client'], $deliveryId),
            'Expired lease was not quarantined.');
        ensure(DB::table('deliveries')->where('id', $deliveryId)
            ->where('state', 'review_required')->whereNull('lease_token')
            ->where('last_error_code', 'LEASE_EXPIRED_UNCERTAIN')->exists(),
            'Quarantined lease retained a token or unexpected state.');
        ensure($leases->claim($f['client'], $deliveryId) === null,
            'A quarantined delivery was reclaimed without review.');
        ensure(DB::table('delivery_attempts')->count() === 0,
            'Lease-only test unexpectedly created a provider attempt.');
        echo "PASS: real MySQL lease fencing, guarded renewal and ambiguous-expiry quarantine.\n";

        runConcurrent($f);
        ensure(DB::table('messages')->count() === 2 && DB::table('deliveries')->count() === 2
            && DB::table('jobs')->count() === 2, 'Concurrent requests duplicated domain or queue rows.');

        // This is a fake-only provider account, not a callable adapter.
        $callbackSecret = bin2hex(random_bytes(32));
        $providerAccountId = DB::table('provider_accounts')->insertGetId([
            'client_id' => $f['client'], 'adapter_code' => 'fake-only',
            'channel' => 'email', 'display_name' => 'Integration-only fake provider',
            'credentials_ciphertext' => Crypt::encryptString(json_encode([
                'callback_secret' => $callbackSecret,
            ], JSON_THROW_ON_ERROR)),
            'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('client_provider_routes')->insert([
            'client_id' => $f['client'], 'provider_account_id' => $providerAccountId,
            'channel' => 'email', 'priority' => 1, 'enabled' => true, 'created_at' => now(),
        ]);
        $attempts = app(DeliveryAttemptManager::class);
        $acceptedMessage = $publisher->publish(
            $f['client'], $f['key'], 'attempt-accepted-'.bin2hex(random_bytes(10)),
            [$f['target']], payload()
        );
        $acceptedDelivery = $acceptedMessage['delivery_ids'][0];
        (new StageDeliveryJob($acceptedDelivery))->handle();
        $acceptedToken = $leases->claim($f['client'], $acceptedDelivery, 60);
        ensure(is_string($acceptedToken), 'Could not lease accepted-case delivery.');
        $acceptedAttemptId = $attempts->begin(
            $f['client'], $acceptedDelivery, $acceptedToken, $providerAccountId
        );
        ensure(is_int($acceptedAttemptId), 'Failed to persist started attempt.');
        ensure(DB::table('delivery_attempts')->where('id', $acceptedAttemptId)
            ->where('outcome', 'started')->exists(), 'Started attempt not durable.');

        ensure($attempts->finish(
            $f['client'], $acceptedDelivery, $acceptedToken,
            $acceptedAttemptId, ProviderAttemptOutcome::Accepted, 202, 'opaque-test-ref-1'
        ), 'Could not finish accepted attempt.');
        ensure(DB::table('deliveries')->where('id', $acceptedDelivery)
            ->where('state', 'accepted')->whereNull('lease_token')->exists(),
            'Provider accepted was incorrectly marked delivered or retained a lease.');
        ensure(DB::table('delivery_attempts')->where('id', $acceptedAttemptId)
            ->where('outcome', 'accepted')->where('provider_ref', 'opaque-test-ref-1')->exists(),
            'Accepted attempt missing normalized result.');
        ensure(! $attempts->finish(
            $f['client'], $acceptedDelivery, $acceptedToken,
            $acceptedAttemptId, ProviderAttemptOutcome::Accepted
        ), 'Duplicate completion of a provider attempt succeeded.');
        echo "PASS: MySQL durable attempt start and accepted-not-delivered result; duplicate finish denied.\n";

        $callbackTime = (string) time();
        $callbackBody = json_encode([
            'event_id' => 'event-'.bin2hex(random_bytes(12)),
            'provider_ref' => 'opaque-test-ref-1',
            'status' => 'delivered',
        ], JSON_THROW_ON_ERROR);
        $callbackSignature = 'sha256='.hash_hmac('sha256',
            $providerAccountId."\n".$callbackTime."\n".$callbackBody, $callbackSecret);
        $receipts = app(FakeSignedCallbackReceipt::class);
        ensure($receipts->receive(
            $providerAccountId, $callbackTime, $callbackBody, $callbackSignature
        ), 'A valid fake-only signed callback was rejected.');
        ensure(! $receipts->receive(
            $providerAccountId, $callbackTime, $callbackBody, $callbackSignature
        ), 'A signed callback replay was applied twice.');
        ensure(! $receipts->receive(
            $providerAccountId, $callbackTime, str_replace('delivered', 'rejected', $callbackBody),
            $callbackSignature
        ), 'An altered signed callback was accepted.');
        ensure(DB::table('deliveries')->where('id', $acceptedDelivery)
            ->where('state', 'delivered')->exists(),
            'A verified fake receipt did not finalize the accepted delivery.');
        ensure(DB::table('callback_receipts')->where('provider_account_id', $providerAccountId)
            ->where('delivery_attempt_id', $acceptedAttemptId)->where('status', 'delivered')
            ->count() === 1, 'Fake callback receipt was not durably deduplicated.');
        echo "PASS: MySQL signed fake receipt verified; delivery updated once and replay/tampering denied.\n";

        $uncertainMessage = $publisher->publish(
            $f['client'], $f['key'], 'attempt-ambiguous-'.bin2hex(random_bytes(10)),
            [$f['target']], payload()
        );
        $uncertainDelivery = $uncertainMessage['delivery_ids'][0];
        (new StageDeliveryJob($uncertainDelivery))->handle();
        $uncertainToken = $leases->claim($f['client'], $uncertainDelivery, 60);
        ensure(is_string($uncertainToken), 'Could not lease ambiguous-case delivery.');
        $uncertainAttemptId = $attempts->begin(
            $f['client'], $uncertainDelivery, $uncertainToken, $providerAccountId
        );
        ensure(is_int($uncertainAttemptId), 'Failed to persist ambiguous-case attempt.');
        ensure($attempts->finish(
            $f['client'], $uncertainDelivery, $uncertainToken,
            $uncertainAttemptId, ProviderAttemptOutcome::Ambiguous
        ), 'Could not record ambiguous attempt.');
        ensure(DB::table('deliveries')->where('id', $uncertainDelivery)
            ->where('state', 'review_required')
            ->where('last_error_code', 'PROVIDER_AMBIGUOUS')->exists(),
            'Ambiguous result did not require operator review.');
        ensure($attempts->begin(
            $f['client'], $uncertainDelivery, $uncertainToken, $providerAccountId
        ) === null, 'Ambiguous attempt was automatically restarted.');
        ensure(DB::table('delivery_attempts')->count() === 2,
            'Unexpected additional provider attempt was created.');
        echo "PASS: MySQL ambiguous result quarantined; no automatic retry or external send.\n";

        // End-to-end fake-only dispatch. It must remain a direct call in this
        // local test, never a production queue or network transport.
        $endToEnd = $publisher->publish(
            $f['client'], $f['key'], 'fake-dispatch-'.bin2hex(random_bytes(10)),
            [$f['target']], payload()
        );
        $endToEndDelivery = $endToEnd['delivery_ids'][0];
        (new StageDeliveryJob($endToEndDelivery))->handle();
        $fakeDispatch = app(FakeDispatchCoordinator::class)->dispatch(
            $f['client'], $endToEndDelivery, $providerAccountId
        );
        ensure(is_array($fakeDispatch)
            && is_int($fakeDispatch['attempt_id'])
            && is_string($fakeDispatch['provider_ref']),
            'The internal fake-only coordinator did not persist a simulated acceptance.');
        ensure(DB::table('deliveries')->where('id', $endToEndDelivery)
            ->where('state', 'accepted')->exists(),
            'Fake dispatch incorrectly claimed completed delivery.');
        ensure(app(FakeDispatchCoordinator::class)->dispatch(
            $f['client'], $endToEndDelivery, $providerAccountId
        ) === null, 'A second fake dispatch was incorrectly accepted.');

        $fakeTime = (string) time();
        $fakeBody = json_encode([
            'event_id' => 'event-'.bin2hex(random_bytes(12)),
            'provider_ref' => $fakeDispatch['provider_ref'],
            'status' => 'delivered',
        ], JSON_THROW_ON_ERROR);
        $fakeSignature = 'sha256='.hash_hmac('sha256',
            $providerAccountId."\n".$fakeTime."\n".$fakeBody, $callbackSecret);
        ensure($receipts->receive($providerAccountId, $fakeTime, $fakeBody, $fakeSignature),
            'Signed fake-only end-to-end receipt was rejected.');
        ensure(DB::table('deliveries')->where('id', $endToEndDelivery)
            ->where('state', 'delivered')->where('attempt_count', 1)->exists(),
            'The fake-only end-to-end message was not completed exactly once.');
        ensure(DB::table('delivery_attempts')->where('delivery_id', $endToEndDelivery)
            ->where('outcome', 'accepted')->count() === 1,
            'The fake-only end-to-end dispatch did not produce exactly one accepted attempt.');
        ensure(DB::table('callback_receipts')->where('provider_account_id', $providerAccountId)
            ->where('delivery_attempt_id', $fakeDispatch['attempt_id'])
            ->where('status', 'delivered')->count() === 1,
            'The fake-only end-to-end delivery did not produce exactly one receipt.');
        echo "PASS: MySQL fake-only end-to-end publish/stage/lease/attempt/receipt; no network sends.\n";
    } finally {
        cleanup($clientId);
        ensureEmpty();
        DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
    }
    echo "PASS: disposable integration schema returned to empty state. No migrations, drops or sends.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: '.$e->getMessage().PHP_EOL);
    exit(1);
}
