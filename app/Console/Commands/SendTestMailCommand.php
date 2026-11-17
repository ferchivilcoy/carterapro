<?php

namespace App\Console\Commands;

use App\Mail\TestMail;
use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendTestMailCommand extends Command
{
    protected $signature = 'mail:test {email : Correo destinatario de la prueba}';

    protected $description = 'Envía un correo de prueba al destinatario indicado para verificar la configuración de correo';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error(sprintf('El correo "%s" no tiene un formato válido.', $email));

            return self::FAILURE;
        }

        $mailer = (string) config('mail.default');
        $host = (string) config('mail.mailers.'.$mailer.'.host', '');
        $port = (string) config('mail.mailers.'.$mailer.'.port', '');

        $this->components->info(sprintf(
            'Usando mailer "%s" (%s:%s) desde %s.',
            $mailer,
            $host,
            $port,
            (string) config('mail.from.address'),
        ));

        if ($mailer === 'smtp' && in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $this->components->warn('El host SMTP es local (Mailpit): el correo quedará capturado en Mailpit y no saldrá a internet.');
        }

        if ($mailer === 'log' || $mailer === 'array') {
            $this->components->warn(sprintf('El mailer "%s" no envía correos: sólo los escribe en la bitácora.', $mailer));
        }

        $company = Setting::companyName();

        try {
            Mail::to($email)->send(new TestMail($company));
        } catch (Throwable $exception) {
            $this->components->error(sprintf('No se pudo enviar el correo: %s', $exception->getMessage()));

            return self::FAILURE;
        }

        $this->components->info(sprintf('Correo de prueba enviado a %s.', $email));

        return self::SUCCESS;
    }
}
