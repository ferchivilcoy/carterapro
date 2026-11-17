<?php

namespace App\Enums;

enum CollectionResult: string
{
    case PromisedToPay = 'promised_to_pay';
    case NoAnswer = 'no_answer';
    case WrongNumber = 'wrong_number';
    case Complaint = 'complaint';
    case PaymentReported = 'payment_reported';
    case NoInterest = 'no_interest';
    case ContactSuccessful = 'contact_successful';

    public function label(): string
    {
        return match ($this) {
            self::PromisedToPay => 'Promised to pay',
            self::NoAnswer => 'No answer',
            self::WrongNumber => 'Wrong number',
            self::Complaint => 'Complaint',
            self::PaymentReported => 'Payment reported (via WhatsApp)',
            self::NoInterest => 'No interest',
            self::ContactSuccessful => 'Successful contact',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PromisedToPay => 'success',
            self::PaymentReported => 'info',
            self::ContactSuccessful => 'primary',
            self::NoAnswer => 'warning',
            self::WrongNumber => 'neutral',
            self::Complaint => 'error',
            self::NoInterest => 'neutral',
        };
    }
}
