<?php

use App\Http\Controllers\PrintAgentController;
use Illuminate\Support\Facades\Route;

Route::prefix('print-agent')->name('print-agent.')->group(function () {
    Route::get('jobs', [PrintAgentController::class, 'jobs'])->name('jobs');
    // Versao atual do agent.mjs, para atualizar o Raspberry com um comando
    Route::get('agente.mjs', [PrintAgentController::class, 'codigoAgente'])->name('codigo');
    Route::post('jobs/{printJob}/done', [PrintAgentController::class, 'done'])->name('jobs.done');
    Route::post('jobs/{printJob}/fail', [PrintAgentController::class, 'fail'])->name('jobs.fail');
});
