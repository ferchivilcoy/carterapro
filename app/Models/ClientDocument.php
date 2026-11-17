<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Models\Scopes\VisibleToAssignedCollectorScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientDocument extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    protected $fillable = [
        'client_id',
        'type',
        'file_name',
        'path',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'type' => DocumentType::class,
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * El archivo vive en el disco privado: se sirve por el controlador, que
     * exige autenticación y aplica el scope de cobrador, nunca por URL pública.
     */
    public function url(): string
    {
        return route('client-documents.download', $this);
    }
}
