<?php

namespace App\Mail;

use App\Models\Client;
use App\Models\Loan;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Aviso de vencimiento dirigido al cliente. Agrupa todos los préstamos del
 * cliente que dispararon recordatorio en la misma corrida, para enviar un
 * único correo por cliente y no uno por préstamo.
 */
class LoanDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, Loan>  $loans  Préstamos con sus cuotas cargadas.
     */
    public function __construct(
        public Client $client,
        public Collection $loans,
        public int $windowDays,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Aviso de vencimiento de cuotas - '.Setting::companyName(),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.loan-due-reminder',
        );
    }
}
