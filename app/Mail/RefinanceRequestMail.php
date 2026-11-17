<?php

namespace App\Mail;

use App\Models\RefinanceRequest;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso al equipo de que un cliente pidió refinanciar un préstamo desde su
 * portal. La refinanciación no ocurre sola: el correo es la entrada a la
 * tarea para que un supervisor la evalúe.
 */
class RefinanceRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RefinanceRequest $refinanceRequest,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Solicitud de refinanciación del cliente '.$this->refinanceRequest->client?->name
                .' - '.Setting::companyName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.refinance-request',
        );
    }
}
