<?php

namespace App\Models;

use App\Enums\CashType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'amount',
        'concept',
        'date',
        'user_id',
        'closed_at',
        'status',
    ];

    protected $casts = [
        'type' => CashType::class,
        'amount' => 'decimal:2',
        'date' => 'date',
        'closed_at' => 'datetime',
        'status' => 'string',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    public function scopeIncomes($query)
    {
        return $query->where('type', CashType::Income);
    }

    public function scopeExpenses($query)
    {
        return $query->where('type', CashType::Expense);
    }
}
