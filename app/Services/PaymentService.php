<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function all(): Collection
    {
        return Payment::with(['transactions', 'invoices'])->orderByDesc('id')->get();
    }

    public function create(array $data): Payment
    {
        return DB::connection('bstore_payment')->transaction(function () use ($data) {
            $transactions = $data['transactions'] ?? [];
            unset($data['transactions']);

            $data['payment_method'] = $data['payment_method'] ?? 'cod';
            $data['amount'] = $data['amount'] ?? 0;
            $data['status'] = $data['status'] ?? 'pending';

            if (($data['status'] ?? null) === 'paid' && empty($data['paid_at'])) {
                $data['paid_at'] = now();
            }

            $payment = Payment::create($data);

            foreach ($transactions as $transaction) {
                $transaction['payment_id'] = $payment->id;
                $transaction['amount'] = $transaction['amount'] ?? $payment->amount;

                PaymentTransaction::create($transaction);
            }

            return $payment->fresh(['transactions', 'invoices']);
        });
    }
}
