<?php

namespace App\Http\Controllers;

use App\Enums\EventType;
use App\Enums\InstallmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Client;
use App\Models\Event;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PrintController extends Controller
{
    public function __construct(
        private readonly PrintService $print,
    ) {}

    public function paymentReceipt(Payment $payment): Response
    {
        $payment->load(['client', 'loan', 'installment']);

        return $this->print->stream(
            'prints.receipt',
            ['payment' => $payment],
            'recibo-'.$payment->receipt_number,
        );
    }

    public function loanPlan(Loan $loan): Response
    {
        $loan->load(['client', 'installments']);

        return $this->print->stream(
            'prints.loan-plan',
            ['loan' => $loan],
            'plan-prestamo-'.$loan->id,
        );
    }

    public function clientStatement(Client $client): Response
    {
        return $this->print->clientStatement($client);
    }

    public function installments(Request $request): Response
    {
        $status = $request->string('status', 'all');
        $period = $request->string('period', 'all');

        $installments = Installment::query()
            ->with('loan.client')
            ->when(
                $status !== 'all' && in_array($status->toString(), array_map(fn ($case) => $case->value, InstallmentStatus::cases())),
                fn ($query) => $query->where('status', $status)
            )
            ->when($period->toString() === 'overdue', fn ($query) => $query->overdue())
            ->when($period->toString() === 'today', fn ($query) => $query->dueToday())
            ->when($period->toString() === 'upcoming', fn ($query) => $query->upcoming())
            ->when($request->filled('search'), fn ($query) => $query->whereHas(
                'loan.client',
                fn ($q) => $q->search($request->string('search'))
            ))
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        return $this->print->stream(
            'prints.installments',
            [
                'installments' => $installments,
                'search' => $request->string('search', ''),
                'statusLabel' => $this->installmentStatusLabel($status->toString()),
                'periodLabel' => $this->installmentPeriodLabel($period->toString()),
            ],
            'listado-de-cuotas',
        );
    }

    public function payments(Request $request): Response
    {
        $method = $request->string('method', 'all');
        $status = $request->string('status', 'all');

        $payments = Payment::query()
            ->with(['client', 'loan', 'installment'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->string('search');

                return $query->where(function ($q) use ($term) {
                    $q->where('receipt_number', 'like', "%{$term}%")
                        ->orWhereHas('client', fn ($query) => $query->search($term));
                });
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('payment_date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('payment_date', '<=', $request->string('to')))
            ->when(
                $method !== 'all' && in_array($method->toString(), array_map(fn ($case) => $case->value, PaymentMethod::cases())),
                fn ($query) => $query->where('method', $method)
            )
            ->when(
                $status !== 'all' && in_array($status->toString(), array_map(fn ($case) => $case->value, PaymentStatus::cases())),
                fn ($query) => $query->where('status', $status)
            )
            ->latest('payment_date')
            ->orderByDesc('id')
            ->get();

        return $this->print->stream(
            'prints.payments',
            [
                'payments' => $payments,
                'search' => $request->string('search', ''),
                'from' => $request->string('from', ''),
                'to' => $request->string('to', ''),
                'methodLabel' => $this->paymentMethodLabel($method->toString()),
                'statusLabel' => $this->paymentStatusLabel($status->toString()),
            ],
            'listado-de-pagos',
        );
    }

    public function events(Request $request): Response
    {
        $selectedType = EventType::tryFrom($request->string('type')->toString());
        $search = $request->string('search', '');

        $events = Event::query()
            ->with(['client', 'loan', 'creator'])
            ->when($selectedType, fn ($query) => $query->ofType($selectedType))
            ->when($search->isNotEmpty(), fn ($query) => $query->whereHas(
                'client',
                fn ($q) => $q->search($search)
            ))
            ->latest('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->print->stream(
            'prints.events',
            [
                'events' => $events,
                'search' => $search->toString(),
                'typeLabel' => $selectedType?->label() ?? 'Todos los tipos',
            ],
            'bitacora-de-eventos',
        );
    }

    public function dueToday(): Response
    {
        $installments = Installment::dueToday()
            ->with('loan.client')
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        return $this->print->stream(
            'prints.due-today',
            ['installments' => $installments, 'date' => now()],
            'a-cobrar-hoy',
        );
    }

    private function installmentStatusLabel(string $status): string
    {
        if ($status === 'all') {
            return 'Todos los estados';
        }

        return InstallmentStatus::tryFrom($status)?->label() ?? 'Todos los estados';
    }

    private function installmentPeriodLabel(string $period): string
    {
        return match ($period) {
            'overdue' => 'Vencidas',
            'today' => 'Vencen hoy',
            'upcoming' => 'Próximas 7 días',
            default => 'Todos los períodos',
        };
    }

    private function paymentMethodLabel(string $method): string
    {
        if ($method === 'all') {
            return 'Todos los métodos';
        }

        return PaymentMethod::tryFrom($method)?->label() ?? 'Todos los métodos';
    }

    private function paymentStatusLabel(string $status): string
    {
        if ($status === 'all') {
            return 'Todos los estados';
        }

        return PaymentStatus::tryFrom($status)?->label() ?? 'Todos los estados';
    }
}
