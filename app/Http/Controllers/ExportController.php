<?php

namespace App\Http\Controllers;

use App\Enums\ClientStatus;
use App\Enums\ClientTier;
use App\Enums\LoanStatus;
use App\Enums\PaymentMethod;
use App\Enums\Role;
use App\Models\CashMovement;
use App\Models\Client;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\Payment;
use App\Models\User;
use App\Services\Money;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga los listados principales como CSV. Los filtros salen de los
 * mismos scopes que usa el índice, así el archivo siempre refleja lo que la
 * pantalla está mostrando.
 */
class ExportController extends Controller
{
    public function loans(Request $request): StreamedResponse
    {
        $loans = Loan::query()
            ->with('client')
            ->filtered($request->string('search')->toString(), $request->string('status', 'all')->toString())
            ->latest('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->csv('prestamos', [
            'ID',
            'Cliente',
            'Documento',
            'Monto',
            'Tasa (%)',
            'Tipo de tasa',
            'Modelo',
            'Cuotas',
            'Frecuencia',
            'Inicio',
            'Total',
            'Saldo',
            'Estado',
            'Entrega',
            'Cuenta destino',
            'Alta',
        ], $loans->map(fn (Loan $loan) => [
            $loan->id,
            $loan->client?->name,
            $loan->client?->document,
            Money::plain((float) $loan->principal_amount),
            $this->decimal((float) $loan->interest_rate, 4),
            $loan->rate_type->label(),
            $loan->amortization_model->label(),
            $loan->installments_count,
            $loan->frequency->label(),
            $loan->start_date?->format('d/m/Y'),
            Money::plain((float) $loan->total_amount),
            Money::plain((float) $loan->pending_balance),
            $loan->status->label(),
            $loan->disbursement_method?->label(),
            $loan->disbursement_account,
            $loan->created_at?->format('d/m/Y H:i'),
        ]));
    }

    public function payments(Request $request): StreamedResponse
    {
        $payments = Payment::query()
            ->with(['client', 'installment'])
            ->filtered(
                $request->string('search')->toString(),
                $request->string('from')->toString(),
                $request->string('to')->toString(),
                $request->string('method', 'all')->toString(),
                $request->string('status', 'all')->toString(),
            )
            ->latest('payment_date')
            ->orderByDesc('id')
            ->get();

        return $this->csv('pagos', [
            'ID',
            'Recibo',
            'Fecha',
            'Cliente',
            'Préstamo',
            'Cuota',
            'Monto',
            'Mora',
            'Método',
            'Estado',
            'Notas',
        ], $payments->map(fn (Payment $payment) => [
            $payment->id,
            $payment->receipt_number,
            $payment->payment_date?->format('d/m/Y'),
            $payment->client?->name,
            '#'.$payment->loan_id,
            $payment->installment?->installment_number,
            Money::plain((float) $payment->amount),
            Money::plain((float) $payment->late_fee_amount),
            $payment->method->label(),
            $payment->status->label(),
            $payment->notes,
        ]));
    }

    public function installments(Request $request): StreamedResponse
    {
        $installments = Installment::query()
            ->with(['loan.client'])
            ->filtered(
                $request->string('search')->toString(),
                $request->string('status', 'all')->toString(),
                $request->string('period', 'all')->toString(),
            )
            ->orderBy('due_date')
            ->orderBy('installment_number')
            ->get();

        return $this->csv('cuotas', [
            'ID',
            'Préstamo',
            'Cliente',
            'Cuota',
            'Vencimiento',
            'Esperado',
            'Pagado',
            'Saldo',
            'Mora',
            'Estado',
        ], $installments->map(fn (Installment $installment) => [
            $installment->id,
            '#'.$installment->loan_id,
            $installment->loan?->client?->name,
            $installment->installment_number,
            $installment->due_date?->format('d/m/Y'),
            Money::plain((float) $installment->expected_amount),
            Money::plain((float) $installment->paid_amount),
            Money::plain((float) $installment->pending_balance),
            Money::plain((float) $installment->late_fee),
            $installment->status->label(),
        ]));
    }

    public function clients(Request $request): StreamedResponse
    {
        $search = $request->string('search')->toString();
        $status = $request->string('status', 'all')->toString();
        $tier = $request->string('tier', 'all')->toString();

        $clients = Client::query()
            ->withCount(['loans' => fn ($query) => $query->where('status', LoanStatus::Active)])
            ->withSum(['loans' => fn ($query) => $query->where('status', LoanStatus::Active)], 'pending_balance')
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when(
                $status !== 'all' && in_array($status, array_map(fn ($case) => $case->value, ClientStatus::cases()), true),
                fn ($query) => $query->where('status', $status),
            )
            ->when(
                $tier !== 'all' && in_array($tier, array_map(fn ($case) => $case->value, ClientTier::cases()), true),
                fn ($query) => $query->where('tier', $tier),
            )
            ->latest('created_at')
            ->orderByDesc('id')
            ->get();

        return $this->csv('clientes', [
            'ID',
            'Cliente',
            'Documento',
            'Teléfono',
            'Email',
            'Domicilio',
            'Nivel',
            'Score',
            'Estado',
            'Cobrador',
            'Préstamos activos',
            'Saldo pendiente',
            'Alta',
        ], $clients->map(fn (Client $client) => [
            $client->id,
            $client->name,
            $client->document,
            $client->phone,
            $client->email,
            $client->address,
            $client->tier?->label(),
            $client->current_score,
            $client->status->label(),
            $client->collector?->name,
            $client->loans_count ?? 0,
            Money::plain((float) ($client->loans_sum_pending_balance ?? 0)),
            $client->created_at?->format('d/m/Y H:i'),
        ]));
    }

    /**
     * Exporta cobranzas por cobrador con clientes, cartera, cobrados y vencidas.
     */
    public function cobranzas(Request $request): StreamedResponse
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        [$fromCarbon, $toCarbon] = $this->period($from, $to);

        $collectors = User::query()
            ->where('role', Role::Cobrador->value)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $rows = [];
        foreach ($collectors as $collector) {
            $clientIds = Client::query()
                ->where('assigned_collector_id', $collector->id)
                ->pluck('id');

            $clientsCount = $clientIds->count();

            $loans = Loan::query()->whereIn('client_id', $clientIds);
            $activeLoans = (int) $loans->where('status', LoanStatus::Active)->count();
            $pendingBalance = (float) $loans->clone()->sum('pending_balance');

            $payments = Payment::active()
                ->whereIn('client_id', $clientIds)
                ->whereBetween('payment_date', [$fromCarbon, $toCarbon])
                ->get();

            $collected = (float) $payments->sum('amount');
            $collectedLateFees = (float) $payments->sum('late_fee_amount');

            $overdueCount = (int) Installment::overdue()
                ->whereHas('loan', fn ($query) => $query->whereIn('client_id', $clientIds))
                ->count();

            $rows[] = [
                $collector->name,
                $clientsCount,
                $activeLoans,
                Money::plain($pendingBalance),
                Money::plain($collected),
                Money::plain($collectedLateFees),
                Money::plain($collected + $collectedLateFees),
                $overdueCount,
            ];
        }

        usort($rows, fn (array $a, array $b) => $b[7] <=> $a[7]);

        return $this->csv('cobranzas-por-cobrador', [
            'Cobrador',
            'Clientes',
            'Préstamos activos',
            'Saldo pendiente',
            'Cobrado capital',
            'Cobrado mora',
            'Total cobrado',
            'Vencidas',
        ], $rows);
    }

    /**
     * Exporta caja diaria: pagos del periodo con desglose por medio de pago y día.
     */
    public function caja(Request $request): StreamedResponse
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();
        $method = $request->string('method', 'all')->toString();

        [$fromCarbon, $toCarbon] = $this->period($from, $to);

        $query = Payment::active()->whereBetween('payment_date', [$fromCarbon, $toCarbon]);

        if ($method !== 'all' && in_array($method, array_map(fn ($case) => $case->value, PaymentMethod::cases()), true)) {
            $query->where('method', $method);
        }

        $payments = $query->orderBy('payment_date')->orderByDesc('id')->get();

        $rows = [];
        foreach ($payments as $payment) {
            $rows[] = [
                $payment->payment_date->format('d/m/Y'),
                $payment->receipt_number,
                $payment->client?->name ?? '—',
                '#'.$payment->loan_id,
                $payment->installment?->installment_number ?? '—',
                Money::plain((float) $payment->amount),
                Money::plain((float) $payment->late_fee_amount),
                Money::plain((float) ($payment->amount + $payment->late_fee_amount)),
                $payment->method->label(),
                $payment->status->label(),
            ];
        }

        return $this->csv('caja-diaria', [
            'Fecha',
            'Recibo',
            'Cliente',
            'Préstamo',
            'Cuota',
            'Capital',
            'Mora',
            'Total',
            'Método',
            'Estado',
        ], $rows);
    }

    /**
     * Exporta rentabilidad: capital cobrado, mora, gastos y rentabilidad neta por mes.
     */
    public function rentabilidad(Request $request): StreamedResponse
    {
        $from = $request->string('from')->toString();
        $to = $request->string('to')->toString();

        [$fromCarbon, $toCarbon] = $this->period($from, $to);

        $months = [];
        $current = $fromCarbon->copy()->startOfMonth();
        $end = $toCarbon->copy()->endOfMonth();

        while ($current <= $end) {
            $startStr = $current->copy()->startOfMonth()->toDateString();
            $endStr = $current->copy()->endOfMonth()->toDateString();

            $payments = Payment::active()->whereBetween('payment_date', [$startStr, $endStr])->get();
            $capitalCollected = (float) $payments->sum(fn ($p) => (float) $p->amount - (float) $p->late_fee_amount);
            $lateFeesCollected = (float) $payments->sum('late_fee_amount');

            $expenses = (float) CashMovement::query()
                ->where('type', 'expense')
                ->whereBetween('date', [$startStr, $endStr])
                ->sum('amount');

            $months[] = [
                $current->format('M Y'),
                Money::plain(round($capitalCollected, 2)),
                Money::plain(round($lateFeesCollected, 2)),
                Money::plain(round($capitalCollected + $lateFeesCollected, 2)),
                Money::plain(round($expenses, 2)),
                Money::plain(round($lateFeesCollected - $expenses, 2)),
            ];

            $current = $current->copy()->addMonth();
        }

        return $this->csv('rentabilidad', [
            'Mes',
            'Capital cobrado',
            'Mora cobrada',
            'Total cobrado',
            'Gastos',
            'Rentabilidad neta',
        ], $months);
    }

    /**
     * Rango de fechas con sensato por defecto: mes en curso hasta hoy.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function period(string $from = '', string $to = ''): array
    {
        $fromCarbon = $from !== '' ? Carbon::parse($from) : now()->startOfMonth();
        $toCarbon = $to !== '' ? Carbon::parse($to) : now();

        return [$fromCarbon->startOfDay(), $toCarbon->endOfDay()];
    }

    /**
     * CSV con separador ";" y números en formato argentino, que es como los
     * abre Excel con la configuración regional del proyecto; el BOM inicial
     * evita que las tildes se rompan.
     *
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    private function csv(string $name, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $name.'-'.today()->toDateString().'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Número con decimales opcionales, para que una tasa 10 quede "10" y una
     * tasa 10.5 quede "10,5" en lugar de arrastrar ceros.
     */
    private function decimal(float $value, int $precision): string
    {
        return rtrim(rtrim(number_format($value, $precision, ',', '.'), '0'), ',');
    }
}
