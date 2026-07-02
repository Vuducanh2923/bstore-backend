<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function all(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(
            self::MAX_PER_PAGE,
            max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE))
        );
        $page = max(1, (int) ($filters['page'] ?? 1));

        return Payment::query()
            ->select(['id', 'order_id', 'payment_method', 'payment_provider', 'transaction_code', 'amount', 'status', 'paid_at'])
            ->with([
                'transactions:id,payment_id,transaction_code,provider,amount,status',
                'invoices:id,payment_id,order_id,invoice_code,total_amount,issued_at',
            ])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
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

    public function createPendingVnpayPayment(int $orderId, float|int|string $amount, string $orderInfo): Payment
    {
        return DB::connection('bstore_payment')->transaction(function () use ($orderId, $amount, $orderInfo) {
            $payment = Payment::create([
                'order_id' => $orderId,
                'payment_method' => 'vnpay',
                'payment_provider' => 'vnpay',
                'amount' => $amount,
                'status' => 'pending',
            ]);

            $payment->transaction_code = (string) $payment->id;
            $payment->save();

            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'transaction_code' => $payment->transaction_code,
                'provider' => 'vnpay',
                'amount' => $payment->amount,
                'status' => 'pending',
                'response_data' => [
                    'event' => 'create_payment_url',
                    'order_info' => $orderInfo,
                ],
            ]);

            return $payment->fresh() ?? $payment;
        });
    }

    public function paymentForVnpayTxnRef(string $txnRef): ?Payment
    {
        $payment = Payment::query()
            ->select(['id', 'order_id', 'payment_method', 'payment_provider', 'transaction_code', 'amount', 'status', 'paid_at'])
            ->where('transaction_code', $txnRef)
            ->orderByDesc('id')
            ->first();

        if ($payment || ! ctype_digit($txnRef)) {
            return $payment;
        }

        return Payment::query()
            ->select(['id', 'order_id', 'payment_method', 'payment_provider', 'transaction_code', 'amount', 'status', 'paid_at'])
            ->find((int) $txnRef);
    }

    public function recordVnpayCallback(Payment $payment, array $payload, bool $successful): Payment
    {
        return DB::connection('bstore_payment')->transaction(function () use ($payment, $payload, $successful) {
            $alreadyPaid = $payment->status === 'paid';
            $status = ($successful || $alreadyPaid) ? 'paid' : 'failed';
            $transactionCode = (string) ($payload['vnp_TransactionNo'] ?? $payload['vnp_TxnRef'] ?? $payment->transaction_code);
            $amount = isset($payload['vnp_Amount']) ? ((int) $payload['vnp_Amount'] / 100) : $payment->amount;

            $payment->status = $status;
            $payment->payment_provider = 'vnpay';
            $payment->paid_at = $status === 'paid' ? ($payment->paid_at ?? now()) : null;
            $payment->save();

            PaymentTransaction::updateOrCreate(
                [
                    'transaction_code' => $transactionCode,
                    'provider' => 'vnpay',
                ],
                [
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'status' => $status,
                    'response_data' => $payload,
                ],
            );

            return $payment->fresh() ?? $payment;
        });
    }

    public function paymentForOrder(int $orderId): ?Payment
    {
        return Payment::query()
            ->select(['id', 'order_id', 'payment_method', 'payment_provider', 'transaction_code', 'amount', 'status', 'paid_at'])
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->first();
    }

    public function invoiceForOrder(int $orderId): ?Invoice
    {
        return Invoice::query()
            ->select(['id', 'payment_id', 'order_id', 'invoice_code', 'total_amount', 'issued_at'])
            ->where('order_id', $orderId)
            ->orderByDesc('id')
            ->first();
    }
}
