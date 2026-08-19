<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    // Lấy toàn bộ dữ liệu.
    public function all(array $filters = []): LengthAwarePaginator
    {
        $perPage = min(self::MAX_PER_PAGE, max(1, (int) ($filters['limit'] ?? $filters['per_page'] ?? self::DEFAULT_PER_PAGE)));
        $page = max(1, (int) ($filters['page'] ?? 1));

        return Payment::query()
            ->with(['transactions', 'invoices', 'refunds'])
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    // Tạo hoặc lưu được xác thực cod.
    public function createAuthoritativeCod(int $orderId, float|int|string $amount): Payment
    {
        return DB::connection('bstore_payment')->transaction(function () use ($orderId, $amount) {
            $payment = Payment::query()->where('order_id', $orderId)->lockForUpdate()->first();

            if ($payment && in_array($payment->status, ['paid', 'refunded', 'partially_refunded'], true)) {
                throw new RuntimeException('Đơn hàng đã được thanh toán');
            }

            $values = [
                'payment_method' => 'cod',
                'payment_provider' => 'cod',
                'transaction_code' => $payment?->transaction_code ?? "COD-{$orderId}",
                'amount' => $amount,
                'status' => 'pending',
                'paid_at' => null,
            ];

            if ($payment) {
                $payment->fill($values)->save();
            } else {
                $payment = Payment::create(['order_id' => $orderId] + $values);
            }

            PaymentTransaction::updateOrCreate(
                ['transaction_code' => $values['transaction_code'], 'provider' => 'cod'],
                ['payment_id' => $payment->id, 'amount' => $amount, 'status' => 'pending', 'response_data' => ['event' => 'cod_created']],
            );

            return $payment->fresh(['transactions']) ?? $payment;
        });
    }

    // Tạo hoặc lưu or reuse đang chờ vnpay thanh toán.
    public function createOrReusePendingVnpayPayment(int $orderId, float|int|string $amount, string $orderInfo, string $createDate): Payment
    {
        return DB::connection('bstore_payment')->transaction(function () use ($orderId, $amount, $orderInfo, $createDate) {
            $payment = Payment::query()->where('order_id', $orderId)->lockForUpdate()->first();

            if ($payment && in_array($payment->status, ['paid', 'refunded', 'partially_refunded'], true)) {
                throw new RuntimeException('Đơn hàng đã được thanh toán');
            }

            $values = [
                'payment_method' => 'vnpay',
                'payment_provider' => 'vnpay',
                'amount' => $amount,
                'status' => 'pending',
                'paid_at' => null,
            ];

            if ($payment) {
                $payment->fill($values)->save();
            } else {
                $payment = Payment::create(['order_id' => $orderId] + $values);
            }

            $payment->transaction_code = sprintf('BST%d%s', $payment->id, strtoupper(Str::random(12)));
            $payment->save();

            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'transaction_code' => $payment->transaction_code,
                'provider' => 'vnpay',
                'amount' => $payment->amount,
                'status' => 'pending',
                'response_data' => ['event' => 'create_payment_url', 'order_info' => $orderInfo, 'vnp_CreateDate' => $createDate],
            ]);

            return $payment->fresh() ?? $payment;
        });
    }

    // Thực hiện thanh toán cho vnpay txn ref.
    public function paymentForVnpayTxnRef(string $txnRef): ?Payment
    {
        return Payment::query()->where('transaction_code', $txnRef)->first();
    }

    // Thực hiện ghi nhận vnpay đồng bộ đang chờ.
    public function recordVnpaySyncPending(Payment $payment, array $payload): Payment
    {
        return $this->recordNotification($payment, $payload, 'sync_pending', false);
    }

    // Thực hiện ghi nhận vnpay paid.
    public function recordVnpayPaid(Payment $payment, array $payload): Payment
    {
        return $this->recordNotification($payment, $payload, 'paid', true);
    }

    // Thực hiện ghi nhận vnpay thất bại.
    public function recordVnpayFailed(Payment $payment, array $payload): Payment
    {
        return $this->recordNotification($payment, $payload, 'failed', true);
    }

    // Thực hiện thanh toán cho đơn hàng.
    public function paymentForOrder(int $orderId): ?Payment
    {
        return Payment::query()->where('order_id', $orderId)->first();
    }

    // Thực hiện synchronize đơn hàng trạng thái.
    public function synchronizeOrderStatus(int $orderId, string $method, float|int|string $amount, string $status): Payment
    {
        return DB::connection('bstore_payment')->transaction(function () use ($orderId, $method, $amount, $status) {
            $payment = Payment::query()->where('order_id', $orderId)->lockForUpdate()->first();
            $method = strtolower($method);
            $status = strtolower($status);
            $isCod = in_array($method, ['cod', 'cash', 'cash_on_delivery'], true);

            if (! $isCod) {
                if (! $payment) {
                    throw new RuntimeException('Không thể cập nhật thanh toán trực tuyến khi chưa có giao dịch.');
                }

                if ($status === 'paid' && ! in_array($payment->status, ['paid', 'partially_refunded'], true)) {
                    throw new RuntimeException('Không thể xác nhận thủ công thanh toán VNPay khi chưa có giao dịch thành công.');
                }

                if ($status !== 'paid' && strtolower((string) $payment->status) !== $status) {
                    throw new RuntimeException('Trạng thái thanh toán trực tuyến phải theo kết quả giao dịch từ Dịch vụ thanh toán.');
                }

                return $payment;
            }

            if (! in_array($status, ['unpaid', 'paid'], true)) {
                throw new RuntimeException('Đơn hàng COD chỉ cho phép trạng thái chưa thanh toán hoặc đã thanh toán.');
            }

            if ($payment && strtolower((string) $payment->status) === 'paid' && $status !== 'paid') {
                throw new RuntimeException('Đơn hàng đã thanh toán không thể chuyển về chưa thanh toán.');
            }

            if (! $payment) {
                $payment = Payment::create([
                    'order_id' => $orderId,
                    'payment_method' => 'cod',
                    'payment_provider' => 'cod',
                    'transaction_code' => "COD-{$orderId}",
                    'amount' => $amount,
                    'status' => $status,
                    'paid_at' => $status === 'paid' ? now() : null,
                ]);
            } elseif (strtolower((string) $payment->status) !== $status) {
                $payment->status = $status;
                $payment->payment_method = 'cod';
                $payment->payment_provider = 'cod';
                $payment->amount = $amount;
                $payment->paid_at = $status === 'paid' ? ($payment->paid_at ?? now()) : null;
                $payment->save();
            }

            return $payment->fresh() ?? $payment;
        });
    }

    // Thực hiện paid vnpay thanh toán cho đơn hàng.
    public function paidVnpayPaymentForOrder(int $orderId): ?Payment
    {
        return Payment::query()
            ->with(['transactions', 'refunds'])
            ->where('order_id', $orderId)
            ->where('payment_provider', 'vnpay')
            ->whereIn('status', ['paid', 'partially_refunded'])
            ->first();
    }

    // Thực hiện hóa đơn cho đơn hàng.
    public function invoiceForOrder(int $orderId): ?Invoice
    {
        return Invoice::query()->where('order_id', $orderId)->first();
    }

    // Thực hiện refunded số tiền.
    public function refundedAmount(Payment $payment): float
    {
        return (float) PaymentRefund::query()
            ->where('payment_id', $payment->id)
            ->whereIn('status', ['refunded', 'processing'])
            ->sum('amount');
    }

    // Thực hiện ghi nhận thông báo.
    private function recordNotification(Payment $payment, array $payload, string $status, bool $updatePayment): Payment
    {
        return DB::connection('bstore_payment')->transaction(function () use ($payment, $payload, $status, $updatePayment) {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);
            $transactionCode = (string) ($payload['vnp_TransactionNo'] ?? '');

            if ($transactionCode === '' || $transactionCode === '0') {
                $transactionCode = $payment->transaction_code.':callback';
            }
            $amount = isset($payload['vnp_Amount']) ? ((int) $payload['vnp_Amount'] / 100) : $payment->amount;

            if ($updatePayment) {
                if ($status === 'paid' || $payment->status !== 'paid') {
                    $payment->status = $status;
                }
                $payment->payment_provider = 'vnpay';
                $payment->paid_at = $payment->status === 'paid' ? ($payment->paid_at ?? now()) : null;
                $payment->save();
            }

            PaymentTransaction::updateOrCreate(
                ['transaction_code' => $transactionCode, 'provider' => 'vnpay'],
                ['payment_id' => $payment->id, 'amount' => $amount, 'status' => $status, 'response_data' => $payload],
            );

            if ($payment->status === 'paid') {
                Invoice::firstOrCreate(
                    ['payment_id' => $payment->id],
                    [
                        'order_id' => $payment->order_id,
                        'invoice_code' => sprintf('INV-%d-%d', $payment->order_id, $payment->id),
                        'total_amount' => $payment->amount,
                        'issued_at' => now(),
                    ],
                );
            }

            return $payment->fresh(['transactions', 'invoices']) ?? $payment;
        });
    }
}
