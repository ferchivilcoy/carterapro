<?php

namespace App\Http\Controllers\Api;

use App\Actions\Payments\CancelPaymentAction;
use App\Actions\Payments\RegisterPaymentAction;
use App\Http\Requests\Payments\CancelPaymentRequest;
use App\Http\Requests\Payments\StorePaymentRequest;
use App\Models\Installment;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $query = Payment::query()->with(['client', 'loan', 'installment']);

        if ($search = $request->string('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('client', function ($cq) use ($search) {
                        $cq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($from = $request->string('from')) {
            $query->whereDate('payment_date', '>=', $from);
        }

        if ($to = $request->string('to')) {
            $query->whereDate('payment_date', '<=', $to);
        }

        $payments = $query->latest('payment_date')->paginate(15)->withQueryString();

        return $this->success($payments, 'Pagos listados');
    }

    public function show(Payment $payment)
    {
        $payment->load(['client', 'loan', 'installment']);

        return $this->success($payment, 'Pago encontrado');
    }

    public function store(StorePaymentRequest $request, RegisterPaymentAction $register)
    {
        $data = $request->validated();
        $data['user_id'] = auth()->id();

        if ($request->hasFile('comprobante')) {
            // Mismo destino privado que el panel web.
            $data['comprobante_path'] = $request->file('comprobante')->store('comprobantes', 'local');
            $data['verification_status'] = 'pendiente';
        } else {
            $data['verification_status'] = 'verificado';
            $data['verified_at'] = now();
            $data['verified_by'] = auth()->id();
        }

        try {
            $payment = $register->execute(
                Installment::findOrFail($request->integer('installment_id')),
                $data,
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($payment->load(['installment', 'loan', 'client']), 'Pago registrado', 201);
    }

    public function cancel(Payment $payment, CancelPaymentRequest $request, CancelPaymentAction $cancel)
    {
        try {
            $cancel->execute($payment, $request->validated('cancellation_reason'));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Pago cancelado');
    }
}
