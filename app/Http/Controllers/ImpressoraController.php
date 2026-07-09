<?php

namespace App\Http\Controllers;

use App\Models\Impressora;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class ImpressoraController extends Controller
{
    private const SECOES = [
        'bebidas' => 'Bebidas',
        'frango' => 'Frango',
        'acompanhamentos' => 'Acompanhamentos',
        'comida' => 'Comida',
        'cozinha' => 'Cozinha',
        'sobremesas' => 'Sobremesas',
        'servico' => 'Servico',
        'bar' => 'Bar',
        'cafe' => 'Cafe',
        'pos' => 'POS',
        'contas' => 'Contas',
    ];

    public function index(): Response
    {
        return Inertia::render('Impressoras/Index', [
            'impressoras' => Impressora::orderBy('nome')->get(),
            'secoes' => self::SECOES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        Impressora::create($validated);

        return back()->with('success', 'Impressora criada com sucesso.');
    }

    public function update(Request $request, Impressora $impressora): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $impressora->update($validated);

        return back()->with('success', 'Impressora atualizada com sucesso.');
    }

    public function destroy(Impressora $impressora): RedirectResponse
    {
        $impressora->delete();

        return back()->with('success', 'Impressora removida com sucesso.');
    }

    public function downloadAgente(): StreamedResponse
    {
        $dir = base_path('local-printer-agent');

        return response()->streamDownload(function () use ($dir): void {
            $tmpFile = tempnam(sys_get_temp_dir(), 'ardc_agent_');
            $zip = new ZipArchive();
            $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            foreach (['agent.mjs', '.env.example', 'README.md'] as $file) {
                $path = $dir.'/'.$file;
                if (file_exists($path)) {
                    $zip->addFile($path, $file);
                }
            }

            $zip->addFromString('ardc-print-agent.service', $this->systemdService());
            $zip->close();

            echo file_get_contents($tmpFile);
            unlink($tmpFile);
        }, 'ardc-print-agent.zip', ['Content-Type' => 'application/zip']);
    }

    private function systemdService(): string
    {
        return "[Unit]\n".
            "Description=ARDC Santana - Agente de Impressao Local\n".
            "After=network.target\n\n".
            "[Service]\n".
            "Type=simple\n".
            "WorkingDirectory=/opt/ardc-print-agent\n".
            "ExecStart=node --env-file=.env agent.mjs\n".
            "Restart=always\n".
            "RestartSec=5\n".
            "StandardOutput=journal\n".
            "StandardError=journal\n\n".
            "[Install]\n".
            "WantedBy=multi-user.target\n";
    }

    private function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'secao' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SECOES))],
            'host' => ['required', 'string', 'max:255'],
            'porta' => ['required', 'integer', 'min:1', 'max:65535'],
            'ativa' => ['boolean'],
        ];
    }
}
