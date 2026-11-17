<?php

namespace App\Models;

use App\Enums\ClientStatus;
use App\Enums\ClientTier;
use App\Enums\LoanStatus;
use App\Enums\RiskLevel;
use App\Models\Scopes\VisibleToAssignedCollectorScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    protected $fillable = [
        'name',
        'phone',
        'email',
        'document',
        'address',
        'alias',
        'cbu_cvu',
        'bank_wallet',
        'account_holder',
        'is_own_account',
        'notes',
        'monthly_income',
        'risk_level',
        'status',
        'current_score',
        'tier',
        'created_by',
        'updated_by',
        'assigned_collector_id',
    ];

    protected $casts = [
        'is_own_account' => 'boolean',
        'current_score' => 'integer',
        'status' => ClientStatus::class,
        'tier' => ClientTier::class,
        'monthly_income' => 'decimal:2',
        'risk_level' => RiskLevel::class,
    ];

    // Relaciones
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Cobrador encargado de este cliente. Null = sin asignar (sólo los roles
     * sin filtro de cobrador lo ven).
     */
    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_collector_id');
    }

    /**
     * Cuentas de usuario vinculadas a este cliente (portal "Mi cuenta").
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ClientDocument::class);
    }

    public function collectionVisits(): HasMany
    {
        return $this->hasMany(CollectionVisit::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', ClientStatus::Active);
    }

    public function scopeSearch($query, string $term)
    {
        return $query->where(function ($q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('document', 'like', "%{$term}%");
        });
    }

    // Accesores
    public function hasActiveLoans(): bool
    {
        return $this->loans()->where('status', LoanStatus::Active)->exists();
    }

    /**
     * Resumen legible de la cuenta/datos bancarios del cliente, o null si no tiene.
     */
    public function accountSummary(): ?string
    {
        $parts = array_values(array_filter([
            $this->bank_wallet ?: null,
            $this->cbu_cvu ? 'CBU/CVU '.$this->cbu_cvu : null,
            $this->alias ? 'Alias: '.$this->alias : null,
            $this->account_holder ? 'Titular: '.$this->account_holder : null,
        ]));

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
