@props([
    'installment',
    'showDueDate' => true,
    'showOverdue' => false,
    'showLateFee' => false,
])

@php
    $client = $installment->loan?->client;
    $daysOverdue = $installment->daysOverdue();
    $status = $installment->status;
    $statusLabel = $status?->label() ?? '—';
    $statusColor = $status?->color() ?? 'neutral';
    $statusColor = ['error' => 'danger', 'neutral' => 'secondary'][$statusColor] ?? $statusColor;
@endphp

<tr {{ $attributes }}>
    <td>
        <div class="d-flex align-items-center gap-2">
            <x-avatar :name="$client?->name ?? 'Cliente'" size="sm" />

            <div class="min-w-0">
                <div class="fw-semibold text-truncate">{{ $client?->name ?? 'Cliente sin asignar' }}</div>
                <div class="small text-secondary text-truncate">{{ $client?->phone ?? 'Sin teléfono' }}</div>
            </div>
        </div>
    </td>

    <td class="text-nowrap">
        @can('loans.view')
            <a href="{{ route('loans.show', $installment->loan_id) }}" class="link-primary">
                #{{ $installment->loan_id }}
            </a>
        @else
            #{{ $installment->loan_id }}
        @endcan
    </td>

    <td class="text-nowrap">#{{ $installment->installment_number }}</td>

    @if ($showDueDate)
        <td class="text-nowrap">{{ $installment->due_date?->format('d/m/Y') }}</td>
    @endif

    <td class="text-nowrap">
        @if ($showOverdue && $daysOverdue > 0)
            <span class="badge bg-danger-lt text-danger">{{ $daysOverdue }} día{{ $daysOverdue === 1 ? '' : 's' }}</span>
        @else
            <span class="badge bg-{{ $statusColor }}-lt text-{{ $statusColor }}">{{ $statusLabel }}</span>
        @endif
    </td>

    @if ($showLateFee)
        <td class="text-end text-nowrap">{{ \App\Services\Money::format($installment->late_fee) }}</td>
    @endif

    <td class="text-end fw-semibold text-nowrap">{{ \App\Services\Money::format($installment->pending_balance) }}</td>

    <td class="text-end">
        @can('loans.view')
            <a
                href="{{ route('loans.show', $installment->loan_id) }}"
                class="btn btn-sm btn-outline-secondary btn-icon"
                title="Ver préstamo"
                aria-label="Ver préstamo #{{ $installment->loan_id }}"
            >
                <i class="ti ti-eye" aria-hidden="true"></i>
            </a>
        @endcan
    </td>
</tr>
