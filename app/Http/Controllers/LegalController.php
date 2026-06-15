<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LegalController extends Controller
{
    public function privacidade(): Response
    {
        return Inertia::render('Legal/Privacidade', [
            'page' => $this->page('privacidade'),
        ]);
    }

    public function termos(): Response
    {
        return Inertia::render('Legal/Termos', [
            'page' => $this->page('termos'),
        ]);
    }

    public function cookies(): Response
    {
        return Inertia::render('Legal/Cookies', [
            'page' => $this->page('cookies'),
        ]);
    }

    private function page(string $slug): array
    {
        return SitePageController::pageData($slug);
    }
}
