<?php

namespace App\Livewire\Hooks;

use App\Exceptions\DatabaseErrorTranslator;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\ComponentHook;

/**
 * Catches QueryException thrown from any Livewire/Filament component action —
 * a resource form save, a table row action, a bulk action — before it can
 * escape as a raw 500 (the "hotels_name_unique" Ignition page this exists to
 * prevent).
 *
 * This has to run here, inside Livewire's own dispatch loop (see
 * Livewire\Wrapped::__call, which triggers the 'exception' hook point for
 * every uncaught \Throwable from a component method call), rather than in
 * app/Exceptions/Handler.php. By the time an exception reaches the global
 * HTTP handler it has already left Livewire's request lifecycle, so setting
 * the component's error bag there wouldn't make Filament highlight the field
 * — Livewire's own SupportValidation hook only wires that up for exceptions
 * caught at this same dispatch boundary. Handler.php remains the safety net
 * for non-Livewire requests (API routes, anything outside a component call).
 */
class TranslatesDatabaseErrors extends ComponentHook
{
    public function exception($e, $stopPropagation)
    {
        if (! $e instanceof QueryException) {
            return;
        }

        $errorId = (string) Str::uuid();

        Log::error("[{$errorId}] Database error: {$e->getMessage()}", [
            'exception' => $e,
        ]);

        $translated = DatabaseErrorTranslator::translate($e);

        $statePath = method_exists($this->component, 'getFormStatePath')
            ? $this->component->getFormStatePath()
            : null;

        if ($translated['field'] && $statePath) {
            // Merge onto whatever's already in the bag rather than replacing it,
            // in case other validation errors are already displayed.
            $this->component->setErrorBag(
                $this->component->getErrorBag()->merge([
                    "{$statePath}.{$translated['field']}" => [$translated['message']],
                ])
            );
        } else {
            Notification::make()
                ->title($translated['title'])
                ->body($translated['message']." (Error ID: {$errorId})")
                ->danger()
                ->persistent()
                ->send();
        }

        $stopPropagation();
    }
}
