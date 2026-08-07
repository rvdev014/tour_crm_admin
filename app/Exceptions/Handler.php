<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * Most database errors from the Filament admin (duplicate hotel name, a
     * delete blocked by a foreign key, ...) never reach this class at all —
     * they're caught right inside Livewire's dispatch loop by
     * App\Livewire\Hooks\TranslatesDatabaseErrors, which is what lets Filament
     * show an inline field error instead of a toast. What's registered here is
     * the safety net for everything that isn't a Livewire component action:
     * plain web routes, the public API, and any other uncaught exception once
     * APP_DEBUG is off. It exists so a raw SQLSTATE/stack trace — like the
     * "hotels_name_unique" Ignition page this was built to stop — can never
     * reach an operator's or a customer's browser again.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // API requests never get HTML or a stack trace, always a small
        // consistent JSON shape with an error id they can quote to support.
        $this->renderable(function (ValidationException $e, Request $request) {
            if (! $this->isApiRequest($request)) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], $e->status);
        });

        $this->renderable(function (QueryException $e, Request $request) {
            $errorId = $this->logWithErrorId($e);
            $translated = DatabaseErrorTranslator::translate($e);

            if ($this->isApiRequest($request)) {
                return response()->json([
                    'message' => $translated['message'],
                    'error_id' => $errorId,
                ], 500);
            }

            return response()->view('errors.500', [
                'errorId' => $errorId,
                'message' => $translated['message'],
            ], 500);
        });

        // Any other uncaught exception on a web/API request, once debug is
        // off. Expected navigational states (404, 403, session expiry, rate
        // limiting, maintenance mode) are left alone so Laravel's normal
        // resources/views/errors/{code}.blade.php lookup still renders them.
        $this->renderable(function (Throwable $e, Request $request) {
            if (config('app.debug')) {
                return null;
            }

            if ($e instanceof HttpExceptionInterface
                || $e instanceof ValidationException
                || $e instanceof AuthenticationException
            ) {
                return null;
            }

            $errorId = $this->logWithErrorId($e);

            if ($this->isApiRequest($request)) {
                return response()->json([
                    'message' => 'Something went wrong. Please try again or contact support.',
                    'error_id' => $errorId,
                ], 500);
            }

            return response()->view('errors.500', ['errorId' => $errorId], 500);
        });
    }

    private function isApiRequest(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function logWithErrorId(Throwable $e): string
    {
        $errorId = (string) Str::uuid();

        Log::error("[{$errorId}] {$e->getMessage()}", ['exception' => $e]);

        return $errorId;
    }
}
