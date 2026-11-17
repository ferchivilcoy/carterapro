<?php

namespace App\Models;

use App\Models\Scopes\VisibleToAssignedCollectorScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contract extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    protected $fillable = [
        'loan_id',
        'contract_number',
        'pdf_path',
        'generated_at',
        'generated_by',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * Accessor (no relación): el cliente se resuelve a través del préstamo.
     */
    public function getClientAttribute(): ?Client
    {
        return $this->loan?->client;
    }
}
