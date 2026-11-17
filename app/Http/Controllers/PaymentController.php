<?php

namespace App\Http\Controllers;

use App\Actions\Payments\CancelPaymentAction;
use App\Actions\Payments\RegisterPaymentAction;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\Payments\CancelPaymentRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Http\Requests\Payments\VerifyPaymentRequest;
use App\Models\Installment;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        // The date bounds are compared with whereDate because payment_date may
        // carry a time part: a plain comparison against a bare Y-m-d string
        // would drop the payments made on the last day of the range.
        $search = $request->string('search');
        $from = $request->string('from');
        $to = $request->string('to');
        $method = $request->string('method', 'all');
        $status = $request->string('status', 'all');

        $payments = Payment::query()
            ->with(['client', 'loan', 'installment'])
            ->filtered($search->toString(), $from->toString(), $to->toString(), $method->toString(), $status->toString())
            ->latest('payment_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Every filter is normalized to a plain string, because the views
        // compare them against '' and 'all' to decide what to reset and which
        // empty state to show.
        return view('payments.index', [
            'payments' => $payments,
            'search' => $search->toString(),
            'from' => $from->toString(),
            'to' => $to->toString(),
            'method' => $method->toString(),
            'status' => $status->toString(),
        ]);
    }

    public function create(Request $request): View
    {
        // El scope incluye las cuotas con mora pendiente de cobro, aunque su
        // capital ya esté saldado.
        $installments = Installment::collectable()
            ->with(['loan.client'])
            ->whereRelation('loan', 'status', '!=', LoanStatus::Cancelled)
            ->orderBy('loan_id')
            ->orderBy('installment_number')
            ->get();

        return view('payments.create', [
            'installments' => $installments,
            'installmentId' => $request->integer('installment_id') ?: null,
            'methodOptions' => PaymentMethod::cases(),
        ]);
    }

    public function store(StorePaymentRequest $request, RegisterPaymentAction $register): RedirectResponse
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        // Manejo de comprobante de pago (foto del transfer/comprobante).
        if ($request->hasFile('comprobante')) {
            // Comprobantes bancarios: disco privado, servidos por controlador.
            $path = $request->file('comprobante')->store('comprobantes', 'local');
            $data['comprobante_path'] = $path;
            // Si hay comprobante, el pago queda pendiente de verificación hasta que el
            // cajero lo confirme contra el banco/billetera.
            $data['verification_status'] = 'pendiente';
        } else {
            // Pago en efectivo: ya se vio el dinero, se marca como verificado.
            $data['verification_status'] = 'verificado';
            $data['verified_at'] = now();
            $data['verified_by'] = auth()->id();
        }

        try {
            $payment = $register->execute(
                Installment::findOrFail($request->integer('installment_id')),
                $data,
            );
        } catch (RuntimeException $e) {
            // La acción es la que decide si la cuota o el préstamo admiten el
            // cobro; el formulario muestra el motivo en vez de caer en un 500.
            return back()->withInput()->with('payment_error', $e->getMessage());
        }

        return redirect()
            ->route('payments.show', $payment)
            ->with('payment_success', ['type' => 'registered', 'receipt' => $payment->receipt_number]);
    }

    public function show(Payment $payment): View
    {
        $payment->load(['client', 'loan', 'installment', 'verifier']);

        return view('payments.show', ['payment' => $payment]);
    }

    /**
     * Sirve el comprobante adjunto desde el disco privado. La ruta está bajo
     * payments.view y el modelo pasa por el scope de cobrador, así que un
     * cobrador no puede abrir el comprobante de un pago ajeno.
     */
    public function comprobante(Payment $payment): Response
    {
        abort_if(blank($payment->comprobante_path), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($payment->comprobante_path), 404);

        return response()->file($disk->path($payment->comprobante_path));
    }

    /**
     * Marca un pago con comprobante como verificado o rechazado. Solo aplica a
     * pagos con verification_status = 'pendiente'.
     */
    public function verify(Payment $payment, VerifyPaymentRequest $request): RedirectResponse
    {
        if (! $payment->isPendingVerification()) {
            return back()->with('payment_error', 'Este pago no está pendiente de verificación.');
        }

        $data = $request->validated();
        $payment->update([
            'verification_status' => $data['verification_status'],
            'verified_at' => now(),
            'verified_by' => auth()->id(),
        ]);

        return redirect()
            ->route('payments.show', $payment)
            ->with('payment_success', ['type' => 'verified', 'status' => $data['verification_status']]);
    }

    public function cancel(Payment $payment, CancelPaymentRequest $request, CancelPaymentAction $cancel): RedirectResponse
    {
        try {
            $cancel->execute($payment, $request->validated('cancellation_reason'));
        } catch (RuntimeException $e) {
            return back()->with('payment_error', $e->getMessage());
        }

        return redirect()
            ->route('payments.index')
            ->with('payment_success', ['type' => 'cancelled', 'receipt' => $payment->receipt_number]);
    }
}
