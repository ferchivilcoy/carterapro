<?php

namespace App\Models;

use App\Enums\AmortizationModel;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Setting extends Model
{
    /**
     * Value applied when a setting has no stored row yet. The settings screen
     * shows these as the effective configuration and the loans create screen
     * falls back to them, so both read the defaults from here.
     *
     * @var array<string, mixed>
     */
    public const DEFAULTS = [
        'company_name' => null,
        'company_logo' => null,
        'min_amount' => 5000,
        'max_initial_amount' => 50000,
        'default_interest_rate' => 10,
        'default_rate_type' => RateType::Total->value,
        'default_amortization_model' => AmortizationModel::FixedInterest->value,
        'default_installments_count' => 6,
        'default_frequency' => PaymentFrequency::Monthly->value,
        'currency' => 'ARS',
        'overdue_grace_days' => 0,
        // Porcentaje mensual de mora sobre el saldo vencido de la cuota;
        // 0 desactiva el cálculo y el cobro de interés punitorio.
        'late_fee_monthly_rate' => 0,
        'reminder_days_before' => 2,
        // '0' = sólo bitácora; '1' = además envía el aviso por email al cliente.
        'reminder_email_enabled' => '0',
        'receipt_prefix' => 'REC-',
        // Pesos del score interno (0-100 cada uno, deben sumar 100). Cuatro
        // señales: cuotas pagadas, ausencia de vencidas, préstamos cerrados y
        // puntualidad (cuotas pagadas sin mora devengada).
        'score_weight_paid' => 60,
        'score_weight_overdue' => 25,
        'score_weight_loans' => 15,
        'score_weight_ontime' => 0,
    ];

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    // Métodos estáticos de acceso rápido
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting?->value ?? $default;
    }

    /**
     * Read several settings at once, keyed by setting key, so a screen never
     * has to query a setting per field. A key without a stored row resolves to
     * its entry on self::DEFAULTS.
     *
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    public static function many(array $keys): array
    {
        $stored = static::query()
            ->whereIn('key', $keys)
            ->pluck('value', 'key');

        $values = [];

        foreach ($keys as $key) {
            $values[$key] = $stored->get($key, static::DEFAULTS[$key] ?? null);
        }

        return $values;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function getDecimal(string $key, float $default = 0): float
    {
        return (float) static::get($key, $default);
    }

    /**
     * Pesos del score interno, normalizados a fracciones que suman 1. Las
     * claves son las de self::SCORE_WEIGHT_KEYS y los valores la fracción del
     * total (p.. 0.60 para 60 puntos). Si los pesos almacenados no suman 100
     * (o no hay filas), se usan los valores por defecto.
     *
     * @return array<string, float>
     */
    public static function scoreWeights(): array
    {
        $defaults = [
            'paid' => (float) (static::DEFAULTS['score_weight_paid'] ?? 60),
            'overdue' => (float) (static::DEFAULTS['score_weight_overdue'] ?? 25),
            'loans' => (float) (static::DEFAULTS['score_weight_loans'] ?? 15),
            'ontime' => (float) (static::DEFAULTS['score_weight_ontime'] ?? 0),
        ];

        $raw = [];
        $total = 0;

        foreach ($defaults as $label => $default) {
            $key = 'score_weight_'.$label;
            $raw[$label] = (float) static::get($key, $default);
            $total += $raw[$label];
        }

        if ($total <= 0) {
            $total = array_sum($defaults);
            $raw = $defaults;
        }

        return array_map(fn (float $value) => round($value / $total, 6), $raw);
    }

    /**
     * Claves de los pesos de scoring, en el orden que se combinan en el score.
     *
     * @return array<int, string>
     */
    public static function scoreWeightKeys(): array
    {
        return [
            'score_weight_paid',
            'score_weight_overdue',
            'score_weight_loans',
            'score_weight_ontime',
        ];
    }

    /**
     * Name that identifies the company on the printed documents. Falls back to
     * the application name, so a fresh install still prints something useful
     * and "LoanVisor" only stands in until the real company is configured.
     */
    public static function companyName(): string
    {
        return static::get('company_name') ?: (string) config('app.name');
    }

    /**
     * Uploaded company logo as a data URI, ready for an <img> tag.
     *
     * The prints are rendered by Dompdf with isRemoteEnabled = false, so a
     * path or a URL would be silently dropped: embedding the bytes as base64
     * keeps the logo inside the document without any remote fetch.
     */
    public static function companyLogoDataUri(): ?string
    {
        $path = static::get('company_logo');
        $disk = Storage::disk('public');

        if (! $path || ! $disk->exists($path)) {
            return null;
        }

        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($disk->get($path));
    }
}
