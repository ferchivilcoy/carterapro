<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use App\Models\Scopes\VisibleToAssignedCollectorScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Installment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    protected $fillable = [
        'loan_id',
        'installment_number',
        'due_date',
        'expected_amount',
        'paid_amount',
        'pending_balance',
        'late_fee',
        'status',
    ];

    protected $casts = [
        'installment_number' => 'integer',
        'due_date' => 'date',
        'expected_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'status' => InstallmentStatus::class,
    ];

    // Relaciones
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function activePayments(): HasMany
    {
        return $this->payments()->where('status', 'active');
    }

    // Scopes
    public function scopePayable($query)
    {
        return $query->whereIn('status', [
            InstallmentStatus::Pending,
            InstallmentStatus::Partial,
            InstallmentStatus::Overdue,
        ]);
    }

    /**
     * Cuotas que el cobro puede tocar: las impagas y las que quedaron con
     * mora devengada aunque su capital esté saldado.
     */
    public function scopeCollectable($query)
    {
        return $query->where(function ($q) {
            $q->payable()
                ->orWhere('late_fee', '>', 0);
        });
    }

    public function scopeDueToday($query)
    {
        return $query->whereDate('due_date', today())->payable();
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', InstallmentStatus::Overdue);
    }

    public function scopeUpcoming($query, int $days = 7)
    {
        return $query->whereDate('due_date', '>=', today())
            ->whereDate('due_date', '<=', today()->addDays($days))
            ->payable();
    }

    /**
     * Listado filtrado que comparten el índice y la exportación CSV, para que
     * ambos respondan exactamente a los mismos parámetros.
     */
    public function scopeFiltered($query, string $search = '', string $status = 'all', string $period = 'all')
    {
        return $query
            ->when(
                $status !== 'all' && in_array($status, array_map(fn ($case) => $case->value, InstallmentStatus::cases()), true),
                fn ($q) => $q->where('status', $status)
            )
            ->when($period === 'overdue', fn ($q) => $q->overdue())
            ->when($period === 'today', fn ($q) => $q->dueToday())
            ->when($period === 'upcoming', fn ($q) => $q->upcoming())
            ->when($search !== '', fn ($q) => $q->whereHas(
                'loan.client',
                fn ($client) => $client->search($search)
            ));
    }

    // Métodos de dominio
    public function isFullyPaid(): bool
    {
        return $this->pending_balance <= 0;
    }

    public function daysOverdue(): int
    {
        if ($this->due_date->isFuture()) {
            return 0;
        }

        return $this->due_date->diffInDays(today());
    }
}
