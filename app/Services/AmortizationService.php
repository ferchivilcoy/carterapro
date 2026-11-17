<?php

namespace App\Services;

use App\Enums\AmortizationModel;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use Carbon\Carbon;
use InvalidArgumentException;

class AmortizationService
{
    /**
     * Devuelve el plan de pagos completo.
     *
     * @return array{total_amount: float, installments: array<int, array{due_date: string, amount: float}>}
     */
    public function calculate(
        float $principal,
        float $interestRate,
        RateType $rateType,
        AmortizationModel $model,
        int $installments,
        string $startDate,
        PaymentFrequency $frequency,
    ): array {
        return match ($model) {
            AmortizationModel::FixedInterest => $this->fixedInterest($principal, $interestRate, $rateType, $installments, $startDate, $frequency),
            AmortizationModel::BalanceInterest => $this->balanceInterest($principal, $interestRate, $rateType, $installments, $startDate, $frequency),
            AmortizationModel::French => $this->french($principal, $interestRate, $rateType, $installments, $startDate, $frequency),
        };
    }

    private function fixedInterest(
        float $principal,
        float $interestRate,
        RateType $rateType,
        int $installments,
        string $startDate,
        PaymentFrequency $frequency,
    ): array {
        $totalInterest = $rateType === RateType::Total
            ? $principal * ($interestRate / 100)
            : $principal * ($interestRate / 100) * $installments;

        $totalAmount = $principal + $totalInterest;
        $installmentAmount = round($totalAmount / $installments, 2);

        // Ajuste por redondeo en la última cuota
        $accumulated = $installmentAmount * ($installments - 1);
        $lastAmount = round($totalAmount - $accumulated, 2);

        $plan = [];

        for ($i = 1; $i <= $installments; $i++) {
            $plan[] = [
                'due_date' => $this->dueDate($startDate, $frequency, $i),
                'amount' => $i === $installments ? $lastAmount : $installmentAmount,
            ];
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'installments' => $plan,
        ];
    }

    /**
     * Cuotas decrecientes: la amortización de capital es constante y el
     * interés de cada período se calcula sobre el saldo que aún queda.
     *
     * @return array{total_amount: float, installments: array<int, array{due_date: string, amount: float}>}
     */
    private function balanceInterest(
        float $principal,
        float $interestRate,
        RateType $rateType,
        int $installments,
        string $startDate,
        PaymentFrequency $frequency,
    ): array {
        $this->assertPeriodicRate($rateType);

        $rate = $interestRate / 100;
        $capitalShare = round($principal / $installments, 2);
        $lastCapitalShare = round($principal - $capitalShare * ($installments - 1), 2);

        $plan = [];
        $balance = $principal;
        $totalAmount = 0.0;

        for ($i = 1; $i <= $installments; $i++) {
            $interest = round($balance * $rate, 2);
            $capital = $i === $installments ? $lastCapitalShare : $capitalShare;
            $amount = round($capital + $interest, 2);

            $plan[] = [
                'due_date' => $this->dueDate($startDate, $frequency, $i),
                'amount' => $amount,
            ];

            $balance = round($balance - $capital, 2);
            $totalAmount += $amount;
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'installments' => $plan,
        ];
    }

    /**
     * Sistema francés: cuota fija en cada período, compuesta por interés sobre
     * el saldo y una parte de capital que crece cuota a cuota.
     *
     * @return array{total_amount: float, installments: array<int, array{due_date: string, amount: float}>}
     */
    private function french(
        float $principal,
        float $interestRate,
        RateType $rateType,
        int $installments,
        string $startDate,
        PaymentFrequency $frequency,
    ): array {
        $this->assertPeriodicRate($rateType);

        $rate = $interestRate / 100;
        $installmentAmount = $rate === 0.0
            ? round($principal / $installments, 2)
            : round($principal * $rate / (1 - (1 + $rate) ** -$installments), 2);

        $plan = [];
        $balance = $principal;
        $totalAmount = 0.0;

        for ($i = 1; $i <= $installments; $i++) {
            $interest = round($balance * $rate, 2);

            // La última cuota amortiza el saldo exacto que quedó, para que el
            // redondeo de las cuotas anteriores no deje centavos pendientes.
            $capital = $i === $installments
                ? round($balance, 2)
                : round($installmentAmount - $interest, 2);

            $amount = round($capital + $interest, 2);

            $plan[] = [
                'due_date' => $this->dueDate($startDate, $frequency, $i),
                'amount' => $amount,
            ];

            $balance = round($balance - $capital, 2);
            $totalAmount += $amount;
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'installments' => $plan,
        ];
    }

    /**
     * Estos dos modelos trabajan con la tasa del período (semanal, quincenal
     * o mensual según la frecuencia); un porcentaje total no tiene una
     * lectura estándar, así que se rechaza en lugar de adivinarla.
     */
    private function assertPeriodicRate(RateType $rateType): void
    {
        if ($rateType !== RateType::Monthly) {
            throw new InvalidArgumentException('Este modelo de amortización solo admite la tasa por período.');
        }
    }

    private function dueDate(string $startDate, PaymentFrequency $frequency, int $installment): string
    {
        return Carbon::parse($startDate)->addDays($frequency->daysBetween() * $installment)->toDateString();
    }
}
