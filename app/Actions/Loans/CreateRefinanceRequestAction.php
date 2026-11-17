<?php

namespace App\Actions\Loans;

use App\Enums\EventType;
use App\Enums\RefinanceRequestStatus;
use App\Enums\Role;
use App\Mail\RefinanceRequestMail;
use App\Models\Event;
use App\Models\Loan;
use App\Models\RefinanceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Registra la solicitud de refinanciación que el cliente levanta desde su
 * portal. No toca el préstamo: sólo deja la tarea pendiente para el equipo,
 * la auditoría en la bitácora y el aviso por email a administradores y
 * supervisores.
 */
class CreateRefinanceRequestAction
{
    public function execute(User $requester, Loan $loan, ?string $message): RefinanceRequest
    {
        $refinanceRequest = RefinanceRequest::create([
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'message' => $message,
            'status' => RefinanceRequestStatus::Pending,
            'created_by' => $requester->id,
        ]);

        Event::create([
            'event_type' => EventType::RefinanceRequested,
            'client_id' => $loan->client_id,
            'loan_id' => $loan->id,
            'extra_data' => [
                'message' => $message,
                'pending_balance' => round((float) $loan->pending_balance, 2),
            ],
            'created_at' => now(),
            'created_by' => $requester->id,
        ]);

        $this->notifyTeam($refinanceRequest->loadMissing(['client', 'loan']));

        return $refinanceRequest;
    }

    /**
     * El aviso no puede frenar la request del cliente: si el servidor de correo
     * falla, la solicitud ya quedó persistida y el error se guarda en el log.
     */
    private function notifyTeam(RefinanceRequest $refinanceRequest): void
    {
        $recipients = User::query()
            ->where('active', true)
            ->whereNotNull('email')
            ->whereIn('role', [Role::Admin->value, Role::Supervisor->value])
            ->pluck('email');

        if ($recipients->isEmpty()) {
            return;
        }

        try {
            Mail::to($recipients->all())
                ->send(new RefinanceRequestMail($refinanceRequest));
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el aviso de solicitud de refinanciación.', [
                'refinance_request_id' => $refinanceRequest->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
