<?php

namespace App\Http\Controllers;

use App\Enums\AmortizationModel;
use App\Enums\PaymentFrequency;
use App\Enums\RateType;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\DatabaseBackupService;
use BackedEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class SettingController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backup,
    ) {}

    public function index(): View
    {
        $fields = $this->fields();
        $values = Setting::many(array_keys($fields));

        $groups = collect($this->groups())
            ->map(fn (array $group, string $groupKey) => [
                'key' => $groupKey,
                ...$group,
                'fields' => collect($fields)
                    ->filter(fn (array $field) => $field['group'] === $groupKey)
                    ->map(fn (array $field, string $key) => [
                        'key' => $key,
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'value' => $values[$key],
                        'options' => $field['options'] ?? [],
                        'step' => $field['step'] ?? null,
                        'min' => $field['min'] ?? null,
                        'max' => $field['max'] ?? null,
                    ])
                    ->values(),
            ])
            ->values();

        return view('settings.index', [
            'groups' => $groups,
            'backups' => $this->backup->files(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $data = $request->validated();

            // The logo arrives as an uploaded file, so its stored path replaces
            // the payload entry: only the path is a setting that can be saved.
            if ($request->hasFile('company_logo')) {
                $data['company_logo'] = $this->storeLogo($request->file('company_logo'));
            } else {
                unset($data['company_logo']);
            }

            foreach ($data as $key => $value) {
                Setting::set($key, $value);
            }
        });

        return redirect()
            ->route('settings.index')
            ->with('settings_success', true);
    }

    /**
     * Streams the uploaded company logo for the preview on the settings
     * screen. The file lives on the public disk, which is not web root
     * reachable without the storage symlink, so it is served from here.
     */
    public function logo(): Response
    {
        $path = Setting::get('company_logo');
        $disk = Storage::disk('public');

        abort_unless($path && $disk->exists($path), 404);

        return response($disk->get($path), 200, [
            'Content-Type' => $disk->mimeType($path) ?: 'image/png',
            'Content-Disposition' => 'inline; filename="company-logo"',
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    /**
     * Saves the uploaded logo under a generated name and drops the previous
     * file, so replacing it never leaves the stored path pointing at nothing.
     */
    private function storeLogo(UploadedFile $logo): string
    {
        $previous = Setting::get('company_logo');

        $path = $logo->store('company', 'public');

        if (! $path) {
            throw new RuntimeException('No se pudo guardar el logo de la empresa.');
        }

        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $path;
    }

    /**
     * Presentation metadata of every setting of the screen. The 'group' entry
     * points at a card of self::groups(), the 'options' one is only read by the
     * fields rendered as a select and the range entries only by the numeric
     * ones, where they mirror the rules of UpdateSettingsRequest.
     *
     * @return array<string, array{label: string, group: string, type: string, options?: array<string, string>, step?: string, min?: string, max?: string}>
     */
    private function fields(): array
    {
        return [
            'company_name' => [
                'label' => 'Nombre de la empresa',
                'group' => 'company',
                'type' => 'text',
            ],
            'company_logo' => [
                'label' => 'Logo (PNG, JPG o WebP, máx. 2 MB)',
                'group' => 'company',
                'type' => 'file',
            ],
            'min_amount' => [
                'label' => 'Monto mínimo',
                'group' => 'amounts',
                'type' => 'number',
                'step' => '0.01',
                'min' => '0',
            ],
            'max_initial_amount' => [
                'label' => 'Monto máximo por préstamo',
                'group' => 'amounts',
                'type' => 'number',
                'step' => '0.01',
                'min' => '0',
            ],
            'default_interest_rate' => [
                'label' => 'Tasa de interés por defecto (%)',
                'group' => 'loan',
                'type' => 'number',
                'step' => '0.01',
                'min' => '0',
            ],
            'default_rate_type' => [
                'label' => 'Tipo de tasa por defecto',
                'group' => 'loan',
                'type' => 'select',
                'options' => $this->options(RateType::cases()),
            ],
            'default_amortization_model' => [
                'label' => 'Modelo de amortización por defecto',
                'group' => 'loan',
                'type' => 'select',
                // Only the models the AmortizationService can calculate, so the
                // default can never point at a plan that throws on creation.
                'options' => $this->options(AmortizationModel::implemented()),
            ],
            'default_installments_count' => [
                'label' => 'Cuotas por defecto',
                'group' => 'loan',
                'type' => 'number',
                'step' => '1',
                'min' => '1',
                'max' => '120',
            ],
            'default_frequency' => [
                'label' => 'Frecuencia por defecto',
                'group' => 'loan',
                'type' => 'select',
                'options' => $this->options(PaymentFrequency::cases()),
            ],
            'overdue_grace_days' => [
                'label' => 'Días de gracia antes de marcar vencida',
                'group' => 'billing',
                'type' => 'number',
                'step' => '1',
                'min' => '0',
            ],
            'late_fee_monthly_rate' => [
                'label' => 'Interés punitorio mensual (%) sobre saldo vencido',
                'group' => 'billing',
                'type' => 'number',
                'step' => '0.01',
                'min' => '0',
                'max' => '100',
            ],
            'reminder_days_before' => [
                'label' => 'Días de anticipación para recordatorios',
                'group' => 'billing',
                'type' => 'number',
                'step' => '1',
                'min' => '0',
                'max' => '60',
            ],
            'reminder_email_enabled' => [
                'label' => 'Enviar recordatorios por email al cliente',
                'group' => 'billing',
                'type' => 'select',
                'options' => ['0' => 'No', '1' => 'Sí'],
            ],
            'receipt_prefix' => [
                'label' => 'Prefijo de recibos',
                'group' => 'billing',
                'type' => 'text',
            ],
            'currency' => [
                'label' => 'Moneda (código ISO)',
                'group' => 'billing',
                'type' => 'text',
            ],
        ];
    }

    /**
     * Cards of the settings screen, in display order.
     *
     * @return array<string, array{label: string, theme: string, icon: string}>
     */
    private function groups(): array
    {
        return [
            'company' => [
                'label' => 'Empresa',
                'theme' => 'warning',
                'icon' => 'buildings',
            ],
            'amounts' => [
                'label' => 'Montos admitidos',
                'theme' => 'primary',
                'icon' => 'cash-banknote',
            ],
            'loan' => [
                'label' => 'Valores por defecto de un préstamo nuevo',
                'theme' => 'info',
                'icon' => 'file-invoice',
            ],
            'billing' => [
                'label' => 'Cobranza',
                'theme' => 'secondary',
                'icon' => 'receipt-2',
            ],
        ];
    }

    /**
     * Build the value => label map of a backed enum for a select field, so the
     * options of the screen never drift from the labels of the domain.
     *
     * @param  array<int, BackedEnum>  $cases
     * @return array<string, string>
     */
    private function options(array $cases): array
    {
        return collect($cases)
            ->mapWithKeys(fn (BackedEnum $case) => [$case->value => $case->label()])
            ->all();
    }
}
