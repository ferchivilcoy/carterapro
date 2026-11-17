<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Cajero = 'cajero';
    case Cobrador = 'cobrador';
    case Cliente = 'cliente';

    /**
     * Whether this role is a portal-only account. Portal accounts belong to a
     * client file and are managed from the Clients module (alta, edición y
     * acceso al portal), never from the Users module.
     */
    public function isPortalAccount(): bool
    {
        return $this === self::Cliente;
    }

    /**
     * The roles an administrator can pick in the Users module: every case
     * except the portal-only ones.
     *
     * @return array<int, Role>
     */
    public static function staffCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $role): bool => ! $role->isPortalAccount(),
        ));
    }

    /**
     * Roles a los que se les puede quitar un módulo. El Administrador conserva
     * el acceso total y el Cliente sólo usa el portal, así que ambos quedan
     * fuera de la matriz de restricciones.
     */
    public function isRestrictable(): bool
    {
        return in_array($this, self::restrictableCases(), true);
    }

    /**
     * Roles internos que aparecen como filas en la matriz de accesos.
     *
     * @return array<int, self>
     */
    public static function restrictableCases(): array
    {
        return [self::Supervisor, self::Cajero, self::Cobrador];
    }

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrador General',
            self::Supervisor => 'Supervisor',
            self::Cajero => 'Cajero',
            self::Cobrador => 'Cobrador',
            self::Cliente => 'Cliente',
        };
    }

    /**
     * Whether this role grants the given ability. The Admin case answers true
     * for every ability so a permission added later is covered automatically;
     * every other role matches only its own allow-list, which keeps them
     * denied by default on anything not registered.
     */
    public function grants(string $ability): bool
    {
        if ($this === self::Admin) {
            return true;
        }

        return in_array($ability, $this->abilities(), true);
    }

    /**
     * Roles whose rows are filtered to the clients they are assigned to.
     */
    public function isCollector(): bool
    {
        return $this === self::Cobrador;
    }

    /**
     * Explicit allow-list used by non-admin roles. Kept as a method so adding
     * a role only requires a new case plus its list, with no Gate changes.
     *
     * @return array<int, string>
     */
    private function abilities(): array
    {
        return match ($this) {
            self::Admin => [],
            self::Supervisor => [
                Ability::DashboardView->value,
                Ability::ClientsView->value,
                Ability::ClientsManage->value,
                Ability::LoansView->value,
                Ability::LoansManage->value,
                Ability::InstallmentsView->value,
                Ability::PaymentsView->value,
                Ability::PaymentsManage->value,
                Ability::EventsView->value,
                Ability::ReportsView->value,
                Ability::ExportsRun->value,
                Ability::CollectionVisitsView->value,
                Ability::CollectionVisitsManage->value,
                Ability::CashMovementsView->value,
                Ability::CashMovementsManage->value,
                Ability::ContractsView->value,
                Ability::ContractsManage->value,
                Ability::ClientDocumentsView->value,
                Ability::ClientDocumentsManage->value,
                Ability::ProfitabilityView->value,
            ],
            self::Cajero => [
                Ability::DashboardView->value,
                Ability::ClientsView->value,
                Ability::LoansView->value,
                Ability::InstallmentsView->value,
                Ability::PaymentsView->value,
                Ability::PaymentsManage->value,
                Ability::ReportsView->value,
                Ability::CollectionVisitsView->value,
                Ability::CollectionVisitsManage->value,
                Ability::CashMovementsView->value,
                Ability::CashMovementsManage->value,
                Ability::ContractsView->value,
                Ability::ContractsManage->value,
                Ability::ClientDocumentsView->value,
                Ability::ClientDocumentsManage->value,
                Ability::ProfitabilityView->value,
            ],
            self::Cobrador => [
                Ability::DashboardView->value,
                Ability::ClientsView->value,
                Ability::LoansView->value,
                Ability::InstallmentsView->value,
                Ability::PaymentsView->value,
                Ability::PaymentsManage->value,
                Ability::ReportsView->value,
                Ability::CollectionVisitsView->value,
                Ability::CollectionVisitsManage->value,
                Ability::CashMovementsView->value,
                Ability::ContractsView->value,
                Ability::ClientDocumentsView->value,
                Ability::ProfitabilityView->value,
            ],
            self::Cliente => [
                Ability::ClientPortal->value,
            ],
        };
    }
}
