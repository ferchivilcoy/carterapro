<?php

namespace App\Models;

use App\Enums\AmortizationModel;
use App\Enums\DisbursementMethod;
use App\Enums\LoanStatus;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Models\Scopes\VisibleToAssignedCollectorScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    protected $fillable = [
        'client_id',
        'refinanced_from_id',
        'principal_amount',
        'interest_rate',
        'rate_type',
        'amortization_model',
        'installments_count',
        'frequency',
        'start_date',
        'total_amount',
        'pending_balance',
        'status',
        'disbursement_method',
        'disbursement_account',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'principal_amount' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'total_amount' => 'decimal:2',
        'pending_balance' => 'decimal:2',
        'installments_count' => 'integer',
        'start_date' => 'date',
        'rate_type' => RateType::class,
        'amortization_model' => AmortizationModel::class,
        'frequency' => PaymentFrequency::class,
        'status' => LoanStatus::class,
        'disbursement_method' => DisbursementMethod::class,
    ];

    // Relaciones
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function parentRefinance(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'refinanced_from_id');
    }

    public function refinancedChildren(): HasMany
    {
        return $this->hasMany(Loan::class, 'refinanced_from_id');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('installment_number');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Solicitudes de refinanciación que el cliente levantó desde su portal.
     */
    public function refinanceRequests(): HasMany
    {
        return $this->hasMany(RefinanceRequest::class);
    }

    /**
     * Contrato PDF generado para este préstamo (uno como máximo en la práctica).
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', LoanStatus::Active);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', LoanStatus::Overdue);
    }

    /**
     * Listado filtrado que comparten el índice y la exportación CSV, para que
     * ambos respondan exactamente a los mismos parámetros.
     */
    public function scopeFiltered($query, string $search = '', string $status = 'all')
    {
        return $query
            ->when(
                $status !== 'all' && in_array($status, array_map(fn ($case) => $case->value, LoanStatus::cases()), true),
                fn ($q) => $q->where('status', $status)
            )
            ->when($search !== '', fn ($q) => $q->whereHas('client', fn ($client) => $client->search($search)));
    }

    // Métodos de dominio
    public function nextPendingInstallment(): ?Installment
    {
        return $this->installments()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->orderBy('installment_number')
            ->first();
    }

    public function paidInstallmentsCount(): int
    {
        return $this->installments()->where('status', 'paid')->count();
    }

    public function remainingInstallmentsCount(): int
    {
        return $this->installments()
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->count();
    }

    public function isFullyPaid(): bool
    {
        return $this->pending_balance <= 0;
    }
}
