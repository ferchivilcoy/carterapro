<?php

namespace App\Actions\Loans;

use App\Enums\AmortizationModel;
use App\Enums\EventType;
use App\Enums\InstallmentStatus;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Models\Event;
use App\Models\Loan;
use App\Services\AmortizationService;
use App\Services\ClientScoreService;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class UpdateLoanAction
{
    /**
     * Campos del plan: si alguno cambia, el plan de amortización se
     * recalcula y las cuotas existentes se reemplazan.
     */
    private const PLAN_FIELDS = [
        'principal_amount',
        'interest_rate',
        'rate_type',
        'amortization_model',
        'installments_count',
        'frequency',
        'start_date',
    ];

    private const TRACKED_FIELDS = [
        ...self::PLAN_FIELDS,
        'disbursement_method',
        'disbursement_account',
        'notes',
    ];

    private const NUMERIC_FIELDS = ['principal_amount', 'interest_rate'];

    public function __construct(
        private readonly AmortizationService $amortization,
        private readonly ClientScoreService $scoreService,
    ) {}

    public function execute(Loan $loan, array $data): Loan
    {
        if (! $loan->status->isEditable()) {
            throw new RuntimeException('Solo se pueden editar préstamos activos o vencidos.');
        }

        $changes = $this->changes($loan, $data);
        $regeneratePlan = (bool) array_intersect($changes, self::PLAN_FIELDS);

        if ($regeneratePlan && $loan->payments()->exists()) {
            throw new RuntimeException('No se pueden cambiar las condiciones de un préstamo con pagos registrados.');
        }

        return DB::transaction(function () use ($loan, $data, $changes, $regeneratePlan) {
            $attributes = ['updated_by' => auth()->id()];

            // Solo se pisan los atributos que el formulario envió: un campo
            // ausente no debe borrar el valor guardado.
            foreach (['disbursement_method', 'disbursement_account', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $attributes[$field] = $data[$field];
                }
            }

            if ($regeneratePlan) {
                // Red de seguridad: las cuotas se borran, y el FK de pagos
                // hacia cuotas (restrictOnDelete) impediría borrar nada si
                // algún pago hubiera quedado registrado.
                if ($loan->payments()->exists()) {
                    throw new RuntimeException('No se pueden cambiar las condiciones de un préstamo con pagos registrados.');
                }

                $plan = $this->amortization->calculate(
                    principal: (float) $data['principal_amount'],
                    interestRate: (float) $data['interest_rate'],
                    rateType: RateType::from($data['rate_type']),
                    model: AmortizationModel::from($data['amortization_model']),
                    installments: (int) $data['installments_count'],
                    startDate: $data['start_date'],
                    frequency: PaymentFrequency::from($data['frequency']),
                );

                $loan->installments()->delete();

                foreach ($plan['installments'] as $index => $installment) {
                    $loan->installments()->create([
                        'installment_number' => $index + 1,
                        'due_date' => $installment['due_date'],
                        'expected_amount' => $installment['amount'],
                        'paid_amount' => 0,
                        'pending_balance' => $installment['amount'],
                        'status' => InstallmentStatus::Pending,
                    ]);
                }

                $attributes += [
                    'principal_amount' => $data['principal_amount'],
                    'interest_rate' => $data['interest_rate'],
                    'rate_type' => $data['rate_type'],
                    'amortization_model' => $data['amortization_model'],
                    'installments_count' => $data['installments_count'],
                    'frequency' => $data['frequency'],
                    'start_date' => $data['start_date'],
                    'total_amount' => $plan['total_amount'],
                    'pending_balance' => $plan['total_amount'],
                    'status' => LoanStatus::Active,
                ];
            }

            $loan->update($attributes);

            if ($changes !== []) {
                Event::create([
                    'event_type' => EventType::LoanUpdated,
                    'client_id' => $loan->client_id,
                    'loan_id' => $loan->id,
                    'extra_data' => [
                        'changes' => $changes,
                        'plan_regenerated' => $regeneratePlan,
                    ],
                    'created_at' => now(),
                    'created_by' => auth()->id(),
                ]);
            }

            $this->scoreService->update($loan->client);

            return $loan->fresh('installments');
        });
    }

    /**
     * Campos cuyo valor difiere del que tiene guardado el préstamo.
     *
     * @return array<int, string>
     */
    private function changes(Loan $loan, array $data): array
    {
        $changes = [];

        foreach (self::TRACKED_FIELDS as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            if ($this->valueChanged($loan, $field, $data[$field])) {
                $changes[] = $field;
            }
        }

        return $changes;
    }

    private function valueChanged(Loan $loan, string $field, mixed $incoming): bool
    {
        $current = $loan->getAttribute($field);

        // Los casts del modelo devuelven Carbon y enums; se normalizan a lo
        // mismo que el formulario envía para poder comparar valor a valor.
        if ($current instanceof CarbonInterface) {
            $current = $current->toDateString();
        }

        if ($current instanceof \BackedEnum) {
            $current = $current->value;
        }

        if ($field === 'notes') {
            return trim((string) $current) !== trim((string) $incoming);
        }

        if (in_array($field, self::NUMERIC_FIELDS, true)) {
            return (float) $current !== (float) $incoming;
        }

        if ($current === null || $incoming === null) {
            return $current !== $incoming;
        }

        return (string) $current !== (string) $incoming;
    }
}
