<?php

namespace App\Models\Scopes;

use App\Models\Client;
use App\Models\Contract;
use App\Models\Installment;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Filtro de filas del rol Cobrador: sólo ve los clientes que tiene asignados
 * y todo lo que les pertenece (préstamos, cuotas, pagos y eventos).
 *
 * Se registra como global scope a propósito, igual que un multi-tenancy: la
 * restricción tiene que valer en todas las rutas de lectura (listados,
 * dashboard, vinculación de rutas e impresiones) sin acordarse de filtrar en
 * cada controlador. Para cualquier otro rol el scope no aplica nada.
 */
class VisibleToAssignedCollectorScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isCollector()) {
            return;
        }

        match (true) {
            $model instanceof Client => $builder->where(
                $model->getTable().'.assigned_collector_id',
                $user->id
            ),
            // Cuotas y contratos => vía préstamo, y el propio scope del
            // préstamo ya restringe a los clientes asignados.
            $model instanceof Installment, $model instanceof Contract => $builder->whereIn(
                $model->getTable().'.loan_id',
                Loan::query()->select('id')
            ),
            default => $builder->whereIn(
                $model->getTable().'.client_id',
                Client::query()->select('id')
            ),
        };
    }
}
