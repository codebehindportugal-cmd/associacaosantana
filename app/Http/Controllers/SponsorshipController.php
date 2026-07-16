<?php

namespace App\Http\Controllers;

use App\Mail\SponsorshipConfirmation;
use App\Mail\SponsorshipReceived;
use App\Models\Sponsor;
use App\Models\SponsorshipRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class SponsorshipController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Public/Patrocinios', [
            'page' => $this->page('patrocinios'),
            'patrocinadores' => $this->patrocinadores(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'empresa' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'mensagem' => ['nullable', 'string', 'max:2000'],
            'aceita_contacto' => ['required', 'accepted'],
            'recaptcha_token' => [new \App\Rules\Recaptcha],
        ], [
            'nome.required' => 'O nome é obrigatório.',
            'empresa.required' => 'O nome da empresa é obrigatório.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Introduza um email válido.',
            'aceita_contacto.accepted' => 'Tem de aceitar ser contactado para submeter a proposta.',
        ]);

        unset($validated['recaptcha_token']);
        $pedido = SponsorshipRequest::create($validated);
        $recipient = config('mail.contact_to') ?: config('mail.from.address');

        try {
            Mail::to($recipient)->send(new SponsorshipReceived($pedido));
            Mail::to($pedido->email)->send(new SponsorshipConfirmation($pedido));
        } catch (Throwable $exception) {
            Log::warning('Falha ao enviar emails de patrocínio.', [
                'pedido_id' => $pedido->id,
                'message' => $exception->getMessage(),
            ]);
        }

        return redirect()
            ->route('patrocinios.index')
            ->with('success', 'Obrigado pelo interesse! Entraremos em contacto brevemente para combinar os detalhes.');
    }

    private function patrocinadores(): Collection
    {
        if (! Schema::hasTable('sponsors')) {
            return collect();
        }

        return Sponsor::where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('empresa')
            ->get();
    }

    private function page(string $slug): array
    {
        return SitePageController::pageData($slug);
    }
}
