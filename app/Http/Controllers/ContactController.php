<?php

namespace App\Http\Controllers;

use App\Mail\ContactConfirmationMail;
use App\Mail\ContactFormMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:160'],
            'phone' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string', 'min:8', 'max:3000'],
            'recaptcha_token' => [new \App\Rules\Recaptcha],
        ]);

        unset($data['recaptcha_token']);

        $recipient = config('mail.contact_to') ?: config('mail.from.address');

        Mail::to($recipient)->send(new ContactFormMail($data));
        Mail::to($data['email'])->send(new ContactConfirmationMail($data));

        return back()->with('success', 'contacto-enviado');
    }
}
