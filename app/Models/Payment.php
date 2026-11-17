<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Scopes\VisibleToAssignedCollectorScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    public const KIND_INSTALLMENT = 'installment';

    public const KIND_PREPAYMENT = 'prepayment';

    protected $fillable = [
        'installment_id',
        'loan_id',
        'client_id',
        'receipt_number',
        'amount',
        'late_fee_amount',
        'payment_date',
        'method',
        'kind',
        'notes',
        'status',
        'cancellation_reason',
        'comprobante_path',
        'verification_status',
        'verified_at',
        'verified_by',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'late_fee_amount' => 'decimal:2',
        'payment_date' => 'date',
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'verification_status' => 'string',
        'verified_at' => 'datetime',
    ];

    // Relaciones
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function isPendingVerification(): bool
    {
        return $this->verification_status === 'pendiente';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verificado';
    }

    public function isRejected(): bool
    {
        return $this->verification_status === 'rechazado';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', PaymentStatus::Active);
    }

    public function scopeCancelled($query)
    {
        return $query->where('status', PaymentStatus::Cancelled);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('payment_date', today());
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        // whereDate on both bounds, so a payment_date that carries a time part
        // is still counted on the last day of the range.
        return $query->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $to);
    }

    /**
     * Listado filtrado que comparten el índice y la exportación CSV, para que
     * ambos respondan exactamente a los mismos parámetros.
     */
    public function scopeFiltered(
        $query,
        string $search = '',
        string $from = '',
        string $to = '',
        string $method = 'all',
        string $status = 'all',
    ) {
        return $query
            ->when($search !== '', fn ($q) => $q->where(
                fn ($inner) => $inner->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('client', fn ($client) => $client->search($search))
            ))
            ->when($from !== '', fn ($q) => $q->whereDate('payment_date', '>=', $from))
            ->when($to !== '', fn ($q) => $q->whereDate('payment_date', '<=', $to))
            ->when(
                $method !== 'all' && in_array($method, array_map(fn ($case) => $case->value, PaymentMethod::cases()), true),
                fn ($q) => $q->where('method', $method)
            )
            ->when(
                $status !== 'all' && in_array($status, array_map(fn ($case) => $case->value, PaymentStatus::cases()), true),
                fn ($q) => $q->where('status', $status)
            );
    }

    // Métodos de dominio
    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::Cancelled;
    }

    public function isPrepayment(): bool
    {
        return $this->kind === self::KIND_PREPAYMENT;
    }
}
