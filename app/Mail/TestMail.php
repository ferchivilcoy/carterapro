<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo de prueba disparado por el comando `mail:test` para verificar que la
 * configuración de correo (mailer, host, remitente) entrega un mensaje real.
 */
class TestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $company,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Prueba de correo - '.$this->company,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.test',
            with: [
                'company' => $this->company,
                'mailer' => (string) config('mail.default'),
                'from' => (string) config('mail.from.address'),
                'sentAt' => now()->format('d/m/Y H:i'),
            ],
        );
    }
}
