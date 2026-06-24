<?php

namespace App\Http\Controllers;

use App\Models\Sponsor;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SponsorScreenController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Public/SponsorScreen', [
            'patrocinadores' => $this->patrocinadores(),
        ]);
    }

    private function patrocinadores(): Collection
    {
        try {
            return Sponsor::where('ativo', true)
                ->where('mostrar_no_slider', true)
                ->with('images')
                ->orderBy('ordem')
                ->orderBy('empresa')
                ->get(['id', 'empresa', 'logotipo', 'ordem']);
        } catch (Throwable) {
            return collect();
        }
    }
}
