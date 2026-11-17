<?php

namespace App\Models;

use App\Enums\RefinanceRequestStatus;
use App\Models\Scopes\VisibleToAssignedCollectorScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Solicitud de refinanciación que el cliente levanta desde su portal. El
 * préstamo no se toca: la fila queda pendiente hasta que el equipo la atiende.
 */
class RefinanceRequest extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    protected $fillable = [
        'client_id',
        'loan_id',
        'message',
        'status',
        'resolved_at',
        'created_by',
        'resolved_by',
    ];

    protected $casts = [
        'status' => RefinanceRequestStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Cuenta de portal que envió la solicitud.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Usuario del equipo que la dio por atendida.
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', RefinanceRequestStatus::Pending);
    }

    public function isPending(): bool
    {
        return $this->status === RefinanceRequestStatus::Pending;
    }
}
