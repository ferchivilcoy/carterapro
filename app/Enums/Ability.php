<?php

namespace App\Enums;

enum Ability: string
{
    case DashboardView = 'dashboard.view';

    case ClientsView = 'clients.view';
    case ClientsManage = 'clients.manage';

    case LoansView = 'loans.view';
    case LoansManage = 'loans.manage';

    case InstallmentsView = 'installments.view';

    case PaymentsView = 'payments.view';
    case PaymentsManage = 'payments.manage';

    case EventsView = 'events.view';

    case SettingsManage = 'settings.manage';
    case BackupManage = 'backup.manage';
    case ExportsRun = 'exports.run';

    case ReportsView = 'reports.view';

    case UsersManage = 'users.manage';

    case ModulesManage = 'modules.manage';

    case ClientPortal = 'clients.portal';

    case CollectionVisitsView = 'collection-visits.view';
    case CollectionVisitsManage = 'collection-visits.manage';

    case CashMovementsView = 'cash-movements.view';
    case CashMovementsManage = 'cash-movements.manage';

    case ContractsView = 'contracts.view';
    case ContractsManage = 'contracts.manage';

    case ClientDocumentsView = 'client-documents.view';
    case ClientDocumentsManage = 'client-documents.manage';

    case ProfitabilityView = 'profitability.view';

    /**
     * Habilidades reservadas al superusuario. Un Administrador General común
     * no las hereda por el bypass de Gate::before: el Gate::define de cada una
     * exige el flag is_super_admin.
     *
     * @return array<int, self>
     */
    public static function superAdminOnly(): array
    {
        return [self::ModulesManage];
    }

    public function isSuperAdminOnly(): bool
    {
        return in_array($this, self::superAdminOnly(), true);
    }
}
