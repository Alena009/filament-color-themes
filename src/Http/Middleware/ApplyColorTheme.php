<?php

namespace AlenaDashko\FilamentColorThemes\Http\Middleware;

use Closure;
use AlenaDashko\FilamentColorThemes\ColorApplier;
use AlenaDashko\FilamentColorThemes\ColorThemeManager;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyColorTheme
{
    public function __construct(
        protected ColorThemeManager $themes,
        protected ColorApplier $applier,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel() ?? Filament::getCurrentOrDefaultPanel();

        if (! $panel?->hasPlugin('filament-color-themes')) {
            return $next($request);
        }

        $this->syncCookieToSession($request);
        $this->applier->apply();

        return $next($request);
    }

    protected function syncCookieToSession(Request $request): void
    {
        $sessionKey = $this->themes->getSessionKey();

        if (session()->has($sessionKey)) {
            return;
        }

        $fromCookie = $request->cookie($sessionKey);

        if (filled($fromCookie) && $this->themes->getThemes()->has($fromCookie)) {
            session()->put($sessionKey, $fromCookie);
        }
    }
}
