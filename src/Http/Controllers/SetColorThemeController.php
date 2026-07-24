<?php

namespace AlenaDashko\FilamentColorThemes\Http\Controllers;

use AlenaDashko\FilamentColorThemes\ColorThemeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;

class SetColorThemeController extends Controller
{
    public function __invoke(Request $request, ColorThemeManager $themes, string $theme): JsonResponse
    {
        if (! $themes->getThemes()->has($theme)) {
            return response()->json(['ok' => false], 422);
        }

        $themes->setTheme($theme);

        return response()
            ->json(['ok' => true, 'theme' => $theme])
            ->withCookie(Cookie::forever($themes->getSessionKey(), $theme));
    }
}
