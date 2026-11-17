<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Setting;

class ReceiptNumberService
{
    public function next(): string
    {
        $prefix = Setting::get('receipt_prefix', 'REC-');
        $last = Payment::where('receipt_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('receipt_number');

        $number = $last
            ? ((int) substr($last, strlen($prefix))) + 1
            : 1;

        return $prefix.str_pad((string) $number, 8, '0', STR_PAD_LEFT);
    }
}
