<?php

namespace App\Services;

use App\Models\Installment;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Interés punitorio: un porcentaje mensual configurado en Settings que se
 * devenga por día sobre el saldo vencido de la cuota, a contar desde que
 * cumplido el día de gracia.
 *
 * La mora se recalcula —no se acumula en forma aditiva— sobre el saldo que
 * aún queda pendiente, y queda congelada en cuanto el capital se salda: una
 * mora ya devengada no desaparece porque después se pague el capital.
 */
class LateFeeService
{
    public function monthlyRate(): float
    {
        return Setting::getDecimal('late_fee_monthly_rate', Setting::DEFAULTS['late_fee_monthly_rate']);
    }

    public function graceDays(): int
    {
        return Setting::getInt('overdue_grace_days', Setting::DEFAULTS['overdue_grace_days']);
    }

    /**
     * Días del mes con los que se convierte la tasa mensual a un devengue
     * diario (tasa mensual / días del mes por día de atraso).
     * Usa los días reales del mes de la fecha de vencimiento para mayor precisión.
     */
    private function daysInMonth(Carbon $date): int
    {
        return (int) $date->copy()->endOfMonth()->format('d');
    }

    /**
     * Mora devengada hasta el día indicado sobre el saldo aún impago.
     *
     * Con capital saldado devuelve la mora guardada, para que el cálculo
     * nunca borre una deuda ya consumada.
     */
    public function calculate(Installment $installment, ?Carbon $asOf = null): float
    {
        $pending = (float) $installment->pending_balance;
        $stored = (float) $installment->late_fee;

        if ($pending <= 0) {
            return $stored;
        }

        $rate = $this->monthlyRate();

        if ($rate <= 0) {
            return $stored;
        }

        $dueDate = $installment->due_date?->copy()->startOfDay()->addDays($this->graceDays());
        $asOfDay = ($asOf ?? today())->copy()->startOfDay();

        // Sin días de atraso (vence hoy o en el futuro) no devenga mora;
        // diffInDays se pide con el vencimiento como origen para que el
        // resultado sea positivo tanto en Carbon 2 como en Carbon 3.
        if ($dueDate === null || $dueDate->gte($asOfDay)) {
            return $stored;
        }

        $daysOverdue = (int) $dueDate->diffInDays($asOfDay);
        $daysInMonth = $this->daysInMonth($dueDate);

        return round($pending * ($rate / 100) * $daysOverdue / $daysInMonth, 2);
    }

    /**
     * Persiste la mora recalculada de una cuota y devuelve el nuevo valor.
     */
    public function refresh(Installment $installment, ?Carbon $asOf = null): float
    {
        $fee = $this->calculate($installment, $asOf);

        if ((float) $installment->late_fee !== $fee) {
            $installment->update(['late_fee' => $fee]);
        }

        return $fee;
    }

    /**
     * Recalcula la mora de todas las cuotas vencidas con saldo pendiente.
     * Corre dentro del sweep diario, así el importe mostrado en pantalla
     * siempre está al día aunque nadie entre a la ficha del préstamo.
     *
     * @return int Filas revisadas
     */
    public function refreshOverdue(): int
    {
        $refreshed = 0;

        Installment::query()
            ->overdue()
            ->where('pending_balance', '>', 0)
            ->orderBy('id')
            ->chunkById(200, function (Collection $installments) use (&$refreshed) {
                foreach ($installments as $installment) {
                    $this->refresh($installment);
                    $refreshed++;
                }
            });

        return $refreshed;
    }
}
