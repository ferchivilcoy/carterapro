@props([
    'installments',
    'showDueDate' => true,
    'showOverdue' => false,
    'showLateFee' => false,
    'emptyTitle' => 'Sin cuotas para mostrar',
    'emptyText' => 'No hay registros en este período.',
    'emptyIcon' => 'calendar-check',
])

@if ($installments->isEmpty())
    <div class="card-body">
        <x-empty-state
            :icon="$emptyIcon"
            :title="$emptyTitle"
            :text="$emptyText"
        />
    </div>
@else
    <div class="table-responsive">
        <table class="table card-table table-vcenter text-nowrap">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Préstamo</th>
                    <th>Cuota</th>
                    @if ($showDueDate)
                        <th>Vencimiento</th>
                    @endif
                    <th>{{ $showOverdue ? 'Mora' : 'Estado' }}</th>
                    @if ($showLateFee)
                        <th class="text-end">Recargo</th>
                    @endif
                    <th class="text-end">Pendiente</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($installments as $installment)
                    <x-dashboard.installment-row
                        :installment="$installment"
                        :show-due-date="$showDueDate"
                        :show-overdue="$showOverdue"
                        :show-late-fee="$showLateFee"
                    />
                @endforeach
            </tbody>
        </table>
    </div>
@endif
