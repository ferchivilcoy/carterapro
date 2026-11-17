<?php

namespace App\Http\Controllers;

use App\Actions\Clients\UpdateClientAction;
use App\Actions\Loans\CreateRefinanceRequestAction;
use App\Enums\InstallmentStatus;
use App\Http\Requests\Portal\StoreRefinanceRequestRequest;
use App\Http\Requests\Portal\UpdateContactRequest;
use App\Http\Requests\Portal\UpdateProfileRequest;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\PrintService;
use App\Services\UserAccessRevoker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RealRashid\SweetAlert\Facades\Alert;

/**
 * Portal "Mi cuenta" del rol Cliente. Todas las consultas se anclan al
 * cliente vinculado a la cuenta (user.client_id): no existe ninguna ruta que
 * reciba el id de un cliente ajeno, y cualquier préstamo que no pertenezca a
 * esa cuenta responde 404.
 */
class ClientPortalController extends Controller
{
    public function __construct(
        private readonly PrintService $print,
        private readonly UserAccessRevoker $accessRevoker,
    ) {}

    public function index(Request $request): View
    {
        $client = $request->user()->client;

        if ($client === null) {
            return view('portal.unlinked');
        }

        $activeStatuses = ['active', 'overdue'];

        return view('portal.index', [
            'client' => $client,
            'activeLoansCount' => $client->loans()->whereIn('status', $activeStatuses)->count(),
            'pendingBalance' => (float) $client->loans()
                ->whereIn('status', $activeStatuses)
                ->sum('pending_balance'),
            'dueInstallments' => Installment::query()
                ->whereIn('loan_id', $client->loans()->select('id'))
                ->whereNotIn('status', [
                    InstallmentStatus::Paid->value,
                    InstallmentStatus::Cancelled->value,
                ])
                ->with('loan')
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->take(5)
                ->get(),
            'totalPaid' => (float) $client->payments()->active()->sum('amount'),
            'recentPayments' => $client->payments()
                ->active()
                ->latest('payment_date')
                ->orderByDesc('id')
                ->take(5)
                ->get(),
        ]);
    }

    public function loans(Request $request): View
    {
        $client = $this->clientOrAbort($request);

        return view('portal.loans', [
            'client' => $client,
            'loans' => $client->loans()
                ->withCount([
                    'installments',
                    'installments as paid_installments_count' => fn ($query) => $query->where('status', 'paid'),
                ])
                ->latest('created_at')
                ->get(),
        ]);
    }

    public function showLoan(Request $request, int $loan): View
    {
        $client = $this->clientOrAbort($request);

        $loan = Loan::query()
            ->whereKey($loan)
            ->whereBelongsTo($client)
            ->with([
                'installments',
                'payments' => fn ($query) => $query->latest('payment_date')->orderByDesc('id'),
                'refinanceRequests' => fn ($query) => $query->latest('created_at')->orderByDesc('id'),
            ])
            ->firstOrFail();

        return view('portal.loan', [
            'client' => $client,
            'loan' => $loan,
            // El formulario sólo aparece si el préstamo sigue abierto con saldo:
            // un préstamo pagado o cancelado no admite solicitudes.
            'canRequestRefinance' => $loan->status->isEditable() && (float) $loan->pending_balance > 0,
        ]);
    }

    public function payments(Request $request): View
    {
        $client = $this->clientOrAbort($request);

        return view('portal.payments', [
            'client' => $client,
            'payments' => $client->payments()
                ->with(['loan', 'installment'])
                ->latest('payment_date')
                ->orderByDesc('id')
                ->paginate(15),
        ]);
    }

    public function installments(Request $request): View
    {
        $client = $this->clientOrAbort($request);

        return view('portal.installments', [
            'client' => $client,
            'installments' => Installment::query()
                ->whereIn('loan_id', $client->loans()->select('id'))
                ->with('loan')
                ->orderBy('due_date')
                ->orderBy('installment_number')
                ->paginate(15),
        ]);
    }

    /**
     * El comprobante se resuelve contra los pagos del cliente vinculado: un
     * recibo ajeno responde 404 en lugar de exponer el de otro cliente.
     */
    public function receipt(Request $request, Payment $payment): Response
    {
        $client = $this->clientOrAbort($request);

        $payment = $client->payments()
            ->whereKey($payment->getKey())
            ->firstOrFail()
            ->load(['client', 'loan', 'installment']);

        return $this->print->stream(
            'prints.receipt',
            ['payment' => $payment],
            'recibo-'.$payment->receipt_number,
        );
    }

    /**
     * Estado de cuenta descargable: el mismo PDF que imprime el panel desde
     * print.clients.statement, resuelto contra el cliente de la cuenta para
     * que nadie pueda pedir el documento de un tercero.
     */
    public function statement(Request $request): Response
    {
        return $this->print->clientStatement($this->clientOrAbort($request));
    }

    /**
     * El perfil no depende de una ficha de cliente vinculada: una cuenta sin
     * vincular igual tiene que poder cambiar su nombre, email o contraseña.
     */
    public function profile(Request $request): View
    {
        return view('portal.profile', [
            'user' => $request->user(),
            'client' => $request->user()->client,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $data = $request->safe()->all();
        $passwordChanged = filled($data['password'] ?? null);

        if (! $passwordChanged) {
            unset($data['password']);
        }

        $user = $request->user();
        $user->update($data);

        // La contraseña nueva cierra las demás sesiones y todos los tokens,
        // pero no la sesión desde la que se está haciendo el cambio.
        if ($passwordChanged) {
            $this->accessRevoker->revokeOthers($user, $request->session()->getId());
        }

        Alert::success('Perfil actualizado', 'Tus datos se guardaron correctamente.');

        return redirect()->route('portal.profile');
    }

    /**
     * Actualiza teléfono, email y domicilio de la ficha del cliente. Reutiliza
     * la acción del panel: la bitácora queda con el mismo formato de cambios
     * y el autor del evento es la cuenta del portal que hizo la edición.
     */
    public function updateContact(UpdateContactRequest $request, UpdateClientAction $update): RedirectResponse
    {
        $client = $this->clientOrAbort($request);

        $update->execute($client, $request->safe()->all());

        Alert::success('Datos de contacto actualizados', 'Guardamos tu teléfono, email y domicilio.');

        return redirect()->route('portal.profile');
    }

    /**
     * Deja la solicitud en la cola del equipo: no refinancia nada por sí sola,
     * sólo persiste la tarea, la audita y avisa por email a supervisor/admin.
     */
    public function storeRefinanceRequest(
        StoreRefinanceRequestRequest $request,
        CreateRefinanceRequestAction $create,
    ): RedirectResponse {
        $client = $this->clientOrAbort($request);

        $loan = Loan::query()
            ->whereKey($request->integer('loan_id'))
            ->whereBelongsTo($client)
            ->firstOrFail();

        if (! $loan->status->isEditable() || (float) $loan->pending_balance <= 0) {
            Alert::error('Solicitud no disponible', 'Este préstamo ya no admite solicitudes de refinanciación.');

            return redirect()->route('portal.loans.show', $loan);
        }

        $create->execute($request->user(), $loan, $request->validated('message'));

        Alert::success('Solicitud enviada', 'Un supervisor va a revisar tu pedido de refinanciación y te vamos a contactar.');

        return redirect()->route('portal.loans.show', $loan);
    }

    /**
     * El portal no existe sin un cliente vinculado: las sub-rutas responden
     * 404 en lugar de mostrar datos vacíos que parecerían un error.
     */
    private function clientOrAbort(Request $request): Client
    {
        return $request->user()->client ?? abort(404);
    }
}
