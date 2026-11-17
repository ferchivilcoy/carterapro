<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\Role;
use App\Models\Scopes\VisibleToAssignedCollectorScope;
use App\Services\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Event extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected static function booted(): void
    {
        static::addGlobalScope(new VisibleToAssignedCollectorScope);
    }

    protected $fillable = [
        'event_type',
        'client_id',
        'loan_id',
        'installment_id',
        'payment_id',
        'extra_data',
        'created_at',
        'created_by',
    ];

    protected $casts = [
        'event_type' => EventType::class,
        'extra_data' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Descripción legible del evento formateada a partir del tipo y extra_data.
     */
    public function getDescriptionAttribute(): string
    {
        $data = $this->extra_data ?? [];

        return match ($this->event_type) {
            EventType::ClientCreated => 'Se registró el cliente en el sistema.',
            EventType::ClientUpdated => $this->describeClientUpdated($data),
            EventType::LoanCreated => 'Préstamo de '.$this->money($data['principal'] ?? null)
                .' en '.(int) ($data['installments'] ?? 0).' cuota(s), entrega por '.$this->deliveryLabel($data['disbursement'] ?? null).'.',
            EventType::LoanClosed => 'El préstamo se cerró al quedar totalmente pagado.',
            EventType::LoanCancelled => 'El préstamo se canceló.'.$this->withReason($data['reason'] ?? null),
            EventType::InstallmentGenerated => 'Se generaron las cuotas del plan de amortización.',
            EventType::InstallmentOverdue => 'La cuota quedó marcada como vencida.',
            EventType::PaymentRegistered => 'Pago de '.$this->money($data['amount'] ?? null)
                .' registrado por '.$this->deliveryLabel($data['method'] ?? null).'.',
            EventType::PaymentCancelled => 'Se anuló el pago y se reabrieron las cuotas.',
            EventType::ReminderGenerated => $this->describeReminder($data),
            EventType::LoanUpdated => $this->describeLoanUpdated($data),
            EventType::LoanRefinanced => $this->describeLoanRefinanced($data),
            EventType::RefinanceRequested => $this->describeRefinanceRequested($data),
            EventType::UserCreated => $this->describeUserCreated($data),
            EventType::UserUpdated => $this->describeUserUpdated($data),
            EventType::LoginSucceeded => $this->describeLogin($data, true),
            EventType::LoginFailed => $this->describeLogin($data, false),
            EventType::Logout => 'La cuenta cerró sesión.',
            EventType::PasswordResetRequested => 'La cuenta pidió un enlace para restablecer la contraseña.',
            EventType::PasswordResetCompleted => 'La cuenta restableció su contraseña.',
            EventType::SuperAdminGranted => $this->describeSuperAdmin($data, true),
            EventType::SuperAdminRevoked => $this->describeSuperAdmin($data, false),
            EventType::ModuleToggled => $this->describeModuleToggled($data),
            EventType::ModuleAccessChanged => $this->describeModuleAccessChanged($data),
            default => ucfirst((string) $this->event_type),
        };
    }

    private function describeLoanUpdated(array $data): string
    {
        $labels = [
            'principal_amount' => 'monto',
            'interest_rate' => 'tasa',
            'rate_type' => 'tipo de tasa',
            'amortization_model' => 'modelo',
            'installments_count' => 'cuotas',
            'frequency' => 'frecuencia',
            'start_date' => 'fecha de inicio',
            'disbursement_method' => 'forma de entrega',
            'disbursement_account' => 'cuenta de destino',
            'notes' => 'notas',
        ];

        $changes = collect($data['changes'] ?? [])
            ->filter(fn ($key) => array_key_exists($key, $labels))
            ->map(fn ($key) => $labels[$key])
            ->values();

        if ($changes->isEmpty()) {
            return 'Se actualizaron los datos del préstamo.';
        }

        $sentence = 'Se actualizaron los datos del préstamo ('.implode(', ', $changes->all()).').';

        if ($data['plan_regenerated'] ?? false) {
            $sentence .= ' Se regeneró el plan de amortización.';
        }

        return $sentence;
    }

    private function describeLoanRefinanced(array $data): string
    {
        $newLoan = (int) ($data['new_loan_id'] ?? 0);
        $balance = $this->money($data['balance'] ?? null);
        $installments = (int) ($data['new_installments'] ?? 0);

        return 'El préstamo se refinanció en el préstamo #'.$newLoan.' por un saldo de '.$balance
            .' en '.$installments.' cuota(s).';
    }

    /**
     * Solicitud que el cliente levantó desde su portal: la bitácora guarda el
     * motivo textual para que el equipo sepa por qué pide la refinanciación.
     */
    private function describeRefinanceRequested(array $data): string
    {
        $sentence = 'El cliente pidió refinanciar el préstamo desde su portal.';

        $message = $data['message'] ?? null;

        if (is_string($message) && trim($message) !== '') {
            $sentence .= ' Motivo: '.trim($message);
        }

        return $sentence;
    }

    private function describeReminder(array $data): string
    {
        $count = (int) ($data['installments'] ?? 0);
        $total = $this->money($data['total'] ?? null);
        $window = (int) ($data['window_days'] ?? 0);
        $next = $data['next_due'] ?? null;

        $sentence = 'Recordatorio diario: '.$count.' cuota(s) por un total de '.$total.' vencidas o por vencer dentro de los próximos '.$window.' día(s).';

        if ($next !== null && $next !== '') {
            $sentence .= ' Próximo vencimiento: '.date('d/m/Y', strtotime((string) $next)).'.';
        }

        return $sentence;
    }

    private function describeClientUpdated(array $data): string
    {
        $labels = [
            'name' => 'nombre',
            'phone' => 'teléfono',
            'email' => 'email de contacto',
            'document' => 'documento',
            'address' => 'domicilio',
            'alias' => 'alias',
            'cbu_cvu' => 'CBU/CVU',
            'bank_wallet' => 'banco/billetera',
            'account_holder' => 'titular de cuenta',
            'is_own_account' => 'cuenta propia',
            'notes' => 'notas',
            'status' => 'estado',
        ];

        $changes = collect($data['changes'] ?? [])
            ->filter(fn ($key) => array_key_exists($key, $labels))
            ->map(fn ($key) => $labels[$key])
            ->values();

        if ($changes->isEmpty()) {
            return 'Se actualizaron los datos del cliente.';
        }

        return 'Se actualizaron los datos del cliente ('.implode(', ', $changes->all()).').';
    }

    private function describeUserCreated(array $data): string
    {
        $email = $data['email'] ?? null;

        $sentence = 'Se creó el usuario '.($data['name'] ?? '').trim(($email ? ' ('.$email.')' : ''))
            .' con el rol '.($data['role'] ?? '—').'.';

        if ($data['is_super_admin'] ?? false) {
            $sentence .= ' Se le otorgó la condición de superusuario.';
        }

        return $sentence;
    }

    private function describeUserUpdated(array $data): string
    {
        $labels = [
            'name' => 'nombre',
            'email' => 'email',
            'password' => 'contraseña',
            'role' => 'rol',
            'active' => 'estado',
            'is_super_admin' => 'condición de superusuario',
        ];

        $changes = collect($data['changes'] ?? [])
            ->filter(fn ($key) => array_key_exists($key, $labels))
            ->map(fn ($key) => $labels[$key])
            ->values();

        $sentence = $changes->isEmpty()
            ? 'Se actualizaron los datos del usuario.'
            : 'Se actualizaron los datos del usuario ('.implode(', ', $changes->all()).').';

        $roleBefore = $data['role_before'] ?? null;
        $roleAfter = $data['role_after'] ?? null;

        if ($roleBefore !== $roleAfter) {
            $sentence .= ' Rol: '.$this->roleLabel($roleBefore).' → '.$this->roleLabel($roleAfter).'.';
        }

        if (array_key_exists('active_before', $data) && $data['active_before'] !== $data['active_after']) {
            $sentence .= ' Estado: '.($data['active_after'] ? 'activo' : 'inactivo').'.';
        }

        return $sentence;
    }

    /**
     * Alta o baja del flag de superusuario: queda registrado a quién se le
     * otorgó o revocó y quién lo hizo, sin exponer credenciales.
     */
    private function describeSuperAdmin(array $data, bool $granted): string
    {
        $name = $data['name'] ?? 'la cuenta';
        $email = $data['email'] ?? null;

        return ($granted ? 'Se otorgó' : 'Se quitó')
            .' la condición de superusuario a '.$name
            .($email ? ' ('.$email.')' : '')
            .'.';
    }

    /**
     * Cambio de acceso de un rol: la Bitácora indica a qué rol se le quitó o
     * habilitó un módulo, con el actor en created_by.
     */
    private function describeModuleAccessChanged(array $data): string
    {
        $role = $data['role_label'] ?? $data['role'] ?? '—';
        $module = $data['module_label'] ?? $data['module'] ?? '—';
        $allowed = (bool) ($data['allowed_after'] ?? false);

        return 'Se '.($allowed ? 'habilitó' : 'quitó').' el módulo '.$module.' al rol '.$role.'.';
    }

    /**
     * Cambio de estado de un módulo: la Bitácora muestra qué módulo se tocó y
     * hacia qué estado pasó, con el actor en created_by.
     */
    private function describeModuleToggled(array $data): string
    {
        $label = $data['module_label'] ?? $data['module'] ?? '—';
        $enabled = (bool) ($data['enabled_after'] ?? false);

        return 'Se '.($enabled ? 'activó' : 'desactivó').' el módulo '.$label.'.';
    }

    /**
     * Auditoría de autenticación: deja registro del correo y la IP, tanto en
     * el login correcto como en el fallido, sin guardar la contraseña.
     */
    private function describeLogin(array $data, bool $succeeded): string
    {
        $sentence = $succeeded
            ? 'Inicio de sesión correcto'
            : 'Intento de inicio de sesión fallido';

        $sentence .= ' para '.($data['email'] ?? '—');

        if (! empty($data['ip'])) {
            $sentence .= ' desde '.$data['ip'];
        }

        return $sentence.'.';
    }

    private function roleLabel(mixed $role): string
    {
        return Role::tryFrom((string) $role)?->label() ?? '—';
    }

    private function money(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return Money::format((float) $value);
    }

    private function deliveryLabel(mixed $method): string
    {
        return match ($method) {
            'cash' => 'efectivo',
            'transfer' => 'transferencia',
            default => 'otro medio',
        };
    }

    private function withReason(mixed $reason): string
    {
        if ($reason === null || $reason === '') {
            return '';
        }

        return ' Motivo: '.$reason.'.';
    }

    // Relaciones
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeOfType($query, EventType $type)
    {
        return $query->where('event_type', $type);
    }

    public function scopeForClient($query, int $clientId)
    {
        return $query->where('client_id', $clientId);
    }
}
