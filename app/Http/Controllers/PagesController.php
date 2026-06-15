<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PagesController extends Controller
{
    public function sobreNos(): Response
    {
        return Inertia::render('Public/SobreNos', [
            'page' => $this->page('sobre-nos'),
        ]);
    }

    private function page(string $slug): array
    {
        return SitePageController::pageData($slug);
    }
}
