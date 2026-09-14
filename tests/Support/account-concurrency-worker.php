<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\LastActiveOwnerException;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use App\Notifications\VerifyEmailCode;
use App\Services\EmailVerification;
use App\Services\OwnerGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\Support\AccountConcurrencyEnvironment;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $settings = json_decode(file_get_contents($argv[1]), true, flags: JSON_THROW_ON_ERROR);
    $job = json_decode(base64_decode($argv[2], true), true, flags: JSON_THROW_ON_ERROR);
    AccountConcurrencyEnvironment::configure($settings['connection'], $settings['prefix']);

    $directory = dirname($argv[1]);
    $worker = $job['worker'];
    $compiledViews = $directory.'/views-'.$worker;
    mkdir($compiledViews, 0700, true);
    config(['view.compiled' => $compiledViews]);
    $user = User::query()->findOrFail($job['user_id']);

    Event::listen(NotificationSending::class, function (NotificationSending $event) use ($directory): void {
        if ($event->notification instanceof VerifyEmailCode) {
            // Keep the real array mail transport; deterministic latency exposes
            // concurrent issuance before the rate limiter has been incremented.
            usleep(200000);
            file_put_contents($directory.'/mail.jsonl', json_encode([
                'user_id' => $event->notifiable->getKey(),
                'code' => $event->notification->code,
            ])."\n", FILE_APPEND | LOCK_EX);
        }
    });

    if ($job['pause_after_code_read'] ?? false) {
        $paused = false;
        DB::listen(function (QueryExecuted $query) use ($directory, $worker, &$paused): void {
            if (! $paused && str_starts_with(strtolower($query->sql), 'select')
                && str_contains(implode(' ', $query->bindings), 'email-verification-code:')) {
                $paused = true;
                touch($directory.'/'.$worker.'.paused');
                AccountConcurrencyEnvironment::waitFor($directory.'/'.$worker.'.resume');
            }
        });
    }

    touch($directory.'/'.$worker.'.ready');
    AccountConcurrencyEnvironment::waitFor($directory.'/'.$job['barrier'].'.go');
    touch($directory.'/'.$worker.'.started');

    if ($job['operation'] === 'send') {
        $result = app(EmailVerification::class)->send($user);
    } elseif ($job['operation'] === 'check') {
        $result = app(EmailVerification::class)->check($user, $job['code']);
    } elseif ($job['operation'] === 'verify') {
        $result = app(EmailVerification::class)->verify($user, $job['code']);
    } elseif ($job['operation'] === 'profile') {
        Auth::setUser($user);
        $request = ProfileUpdateRequest::create('/profile', 'PATCH', ['name' => $user->name, 'email' => $job['email']]);
        $request->setUserResolver(fn () => $user);
        $request->setContainer($app)->setRedirector(app('redirect'));
        $request->setLaravelSession(app('session.store'));
        $app->instance('request', $request);
        $request->validateResolved();

        $result = app(ProfileController::class)->update($request)->getStatusCode();
    } elseif ($job['operation'] === 'mutate') {
        OwnerGuard::mutate($user, function (User $locked) use ($job): void {
            usleep(150000);
            if ($job['field'] === 'role') {
                $locked->role = UserRole::from($job['value']);
            } elseif ($job['field'] === 'status') {
                $locked->status = UserStatus::from($job['value']);
            } elseif ($job['field'] === 'delete') {
                $locked->delete();

                return;
            } else {
                throw new RuntimeException('Unexpected mutation field.');
            }
            $locked->save();
        });
        $result = 'changed';
    } elseif ($job['operation'] === 'controller') {
        $actor = User::query()->findOrFail($job['actor_id']);
        Auth::setUser($actor);
        $request = Request::create('/admin/users/'.$user->id.'/'.$job['field'], 'PATCH', [
            $job['field'] => $job['value'],
        ]);
        $request->setUserResolver(fn () => $actor);
        $request->setLaravelSession(app('session.store'));
        $app->instance('request', $request);

        if ($job['pause_after_authorization'] ?? false) {
            $paused = false;
            Gate::after(function (User $actor, string $ability, ?bool $allowed) use ($directory, $worker, &$paused): void {
                if (! $paused && $allowed && in_array($ability, ['updateRole', 'updateStatus'], true)) {
                    $paused = true;
                    touch($directory.'/'.$worker.'.paused');
                    AccountConcurrencyEnvironment::waitFor($directory.'/'.$worker.'.resume');
                }
            });
        }

        $response = $job['field'] === 'role'
            ? app(UserController::class)->updateRole($request, $user)
            : app(UserController::class)->updateStatus($request, $user);
        $result = $response->getStatusCode();
    } else {
        throw new RuntimeException('Unexpected concurrency worker operation.');
    }

    echo json_encode(['result' => $result], JSON_THROW_ON_ERROR);
} catch (LastActiveOwnerException) {
    echo json_encode(['result' => 'last-owner']);
} catch (AuthorizationException) {
    echo json_encode(['result' => 403]);
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage()."\n".$e->getTraceAsString());
    exit(1);
}
