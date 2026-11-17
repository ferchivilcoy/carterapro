<?php

namespace App\Enums;

enum EventType: string
{
    case ClientCreated = 'client_created';
    case ClientUpdated = 'client_updated';
    case LoanCreated = 'loan_created';
    case LoanClosed = 'loan_closed';
    case LoanCancelled = 'loan_cancelled';
    case InstallmentGenerated = 'installment_generated';
    case InstallmentOverdue = 'installment_overdue';
    case PaymentRegistered = 'payment_registered';
    case PaymentCancelled = 'payment_cancelled';
    case ReminderGenerated = 'reminder_generated';
    case LoanUpdated = 'loan_updated';
    case LoanRefinanced = 'loan_refinanced';
    case RefinanceRequested = 'refinance_requested';
    case UserCreated = 'user_created';
    case UserUpdated = 'user_updated';
    case LoginSucceeded = 'login_succeeded';
    case LoginFailed = 'login_failed';
    case Logout = 'logout';
    case PasswordResetRequested = 'password_reset_requested';
    case PasswordResetCompleted = 'password_reset_completed';
    case SuperAdminGranted = 'super_admin_granted';
    case SuperAdminRevoked = 'super_admin_revoked';
    case ModuleToggled = 'module_toggled';
    case ModuleAccessChanged = 'module_access_changed';

    public function label(): string
    {
        return match ($this) {
            self::ClientCreated => 'Cliente creado',
            self::ClientUpdated => 'Cliente actualizado',
            self::LoanCreated => 'Préstamo creado',
            self::LoanClosed => 'Préstamo cerrado',
            self::LoanCancelled => 'Préstamo cancelado',
            self::LoanUpdated => 'Préstamo editado',
            self::LoanRefinanced => 'Préstamo refinanciado',
            self::RefinanceRequested => 'Solicitud de refinanciación',
            self::InstallmentGenerated => 'Cuota generada',
            self::InstallmentOverdue => 'Cuota vencida',
            self::PaymentRegistered => 'Pago registrado',
            self::PaymentCancelled => 'Pago anulado',
            self::ReminderGenerated => 'Recordatorio generado',
            self::UserCreated => 'Usuario creado',
            self::UserUpdated => 'Usuario actualizado',
            self::LoginSucceeded => 'Inicio de sesión',
            self::LoginFailed => 'Inicio de sesión fallido',
            self::Logout => 'Cierre de sesión',
            self::PasswordResetRequested => 'Recuperación de contraseña solicitada',
            self::PasswordResetCompleted => 'Contraseña restablecida',
            self::SuperAdminGranted => 'Superusuario otorgado',
            self::SuperAdminRevoked => 'Superusuario revocado',
            self::ModuleToggled => 'Cambio de módulo',
            self::ModuleAccessChanged => 'Cambio de acceso por rol',
        };
    }
}
