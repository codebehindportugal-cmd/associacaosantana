<?php

use App\Http\Controllers\PrintAgentController;
use Illuminate\Support\Facades\Route;

Route::prefix('print-agent')->name('print-agent.')->group(function () {
    Route::get('jobs', [PrintAgentController::class, 'jobs'])->name('jobs');
    Route::post('jobs/{printJob}/done', [PrintAgentController::class, 'done'])->name('jobs.done');
    Route::post('jobs/{printJob}/fail', [PrintAgentController::class, 'fail'])->name('jobs.fail');
});
