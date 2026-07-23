<?php

namespace Dashk\FilamentColorThemes\Http\Controllers;

use Dashk\FilamentColorThemes\ColorThemeManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cookie;

class ClearColorThemeController extends Controller
{
    public function __invoke(Request $request, ColorThemeManager $themes): JsonResponse
    {
        $themes->clearTheme();

        return response()
            ->json(['cleared' => true])
            ->withCookie(Cookie::forget($themes->getSessionKey()));
    }
}
