<?php

namespace App\Models;

use App\Enums\CollectionResult;
use App\Enums\CollectionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectionVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'loan_id',
        'installment_id',
        'payment_id',
        'type',
        'result',
        'visited_at',
        'visited_at_time',
        'commitment_date',
        'commitment_amount',
        'notes',
        'managed_by',
    ];

    protected $casts = [
        'type' => CollectionType::class,
        'result' => CollectionResult::class,
        'visited_at' => 'date',
        'visited_at_time' => 'datetime',
        'commitment_date' => 'date',
        'commitment_amount' => 'decimal:2',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'managed_by');
    }

    public function scopeVisitedToday($query)
    {
        return $query->whereDate('visited_at', today());
    }

    public function scopePendingContact($query)
    {
        return $query->whereIn('result', [
            CollectionResult::NoAnswer,
            CollectionResult::WrongNumber,
        ]);
    }

    public function scopePaymentReported($query)
    {
        return $query->where('result', CollectionResult::PaymentReported);
    }

    public function whatsappLink(): ?string
    {
        if (! $this->client?->phone) {
            return null;
        }

        $phone = preg_replace('/\D/', '', $this->client->phone);

        return 'https://wa.me/'.$phone;
    }

    public function messageTemplate(string $type): string
    {
        return match ($type) {
            'reminder' => "Hola {$this->client->name}, te recordamos que tenés una cuota vencida de tu préstamo en Credisoft. Podés ponerte en contacto para regularizar tu situación.",
            'confirmation' => "Hola {$this->client->name}, gracias por informarnos tu pago. Estamos verificando el comprobante y te confirmaremos por este mismo medio.",
            'late_fee' => "Hola {$this->client->name}, tu cuota tiene mora devengada. Te invitamos a ponerte al día para evitar cargos adicionales.",
            default => "Hola {$this->client->name}, te saludamos de Credisoft.",
        };
    }
}
