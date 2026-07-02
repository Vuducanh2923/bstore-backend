<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OrderServiceClient;
use App\Services\PaymentService;
use App\Services\VnpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly VnpayService $vnpayService,
        private readonly OrderServiceClient $orderServiceClient,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentService->all($request->only(['page', 'limit', 'per_page']));

        return response()->json([
            'success' => true,
            'message' => 'Lay danh sach thanh toan thanh cong',
            'data' => $payments->items(),
            'pagination' => [
                'page' => $payments->currentPage(),
                'limit' => $payments->perPage(),
                'total' => $payments->total(),
                'totalPages' => $payments->lastPage(),
            ],
        ]);
    }

    public function paymentByOrder(int|string $orderId): JsonResponse
    {
        $payment = $this->paymentService->paymentForOrder((int) $orderId);

        if (! $payment) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay thanh toan',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay thong tin thanh toan thanh cong',
            'data' => [
                'payment_method' => $payment->payment_method,
                'payment_provider' => $payment->payment_provider,
                'transaction_code' => $payment->transaction_code,
                'amount' => $payment->amount,
                'status' => $payment->status,
                'paid_at' => $payment->paid_at,
            ],
        ]);
    }

    public function invoiceByOrder(int|string $orderId): JsonResponse
    {
        $invoice = $this->paymentService->invoiceForOrder((int) $orderId);

        if (! $invoice) {
            return response()->json([
                'success' => false,
                'message' => 'Khong tim thay hoa don',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lay thong tin hoa don thanh cong',
            'data' => [
                'invoice_code' => $invoice->invoice_code,
                'total_amount' => $invoice->total_amount,
                'issued_at' => $invoice->issued_at,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'order_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string', 'max:50'],
            'payment_provider' => ['nullable', 'string', 'max:50'],
            'transaction_code' => ['nullable', 'string', 'max:191'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', 'max:20'],
            'paid_at' => ['nullable', 'date'],
            'transactions' => ['sometimes', 'array'],
            'transactions.*.transaction_code' => ['required_with:transactions', 'string', 'max:191'],
            'transactions.*.provider' => ['required_with:transactions', 'string', 'max:100'],
            'transactions.*.amount' => ['required_with:transactions', 'numeric', 'min:0'],
            'transactions.*.status' => ['required_with:transactions', 'string', 'max:20'],
            'transactions.*.response_data' => ['nullable'],
        ]);

        $payment = $this->paymentService->create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tao thanh toan thanh cong',
            'data' => $payment,
        ], 201);
    }

    public function createVnpay(Request $request): JsonResponse
    {
        Log::debug('VNPAY create payload', $request->all());
        $this->logVnpayCreateAuthContext($request);

        $validator = Validator::make($request->all(), [
            'order_id' => ['required', 'integer'],
            'amount' => [
                'required',
                'numeric',
                'min:1000',
                function (string $attribute, mixed $value, callable $fail): void {
                    if (is_numeric($value) && floor((float) $value) !== (float) $value) {
                        $fail('amount phai la so nguyen VND');
                    }
                },
            ],
            'order_info' => ['nullable', 'string', 'max:255'],
        ], [
            'order_id.required' => 'order_id la bat buoc',
            'order_id.integer' => 'order_id phai la so nguyen',
            'amount.required' => 'amount la bat buoc',
            'amount.numeric' => 'amount phai la so',
            'amount.min' => 'amount phai lon hon hoac bang 1000',
            'order_info.string' => 'order_info phai la chuoi',
            'order_info.max' => 'order_info khong duoc vuot qua 255 ky tu',
        ]);

        if ($validator->fails()) {
            return $this->vnpayCreateValidationError($request, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        try {
            $result = $this->vnpayService->createPaymentUrl($data, $this->clientIp($request));
        } catch (Throwable $exception) {
            $orderUpdate = $this->orderServiceClient->markPaymentFailed(
                (int) $data['order_id'],
                'Khong tao duoc URL thanh toan VNPAY: '.$exception->getMessage(),
                $request->header('Authorization'),
            );

            Log::error('vnpay.create_payment_url_failed', [
                'request' => $data,
                'message' => $exception->getMessage(),
                'order_update' => $orderUpdate,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Khong tao duoc URL thanh toan VNPAY',
                'error' => $exception->getMessage(),
                'payload' => $request->all(),
                'data' => [
                    'order_update' => $orderUpdate,
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tao URL thanh toan VNPAY thanh cong',
            'data' => $result,
        ], 201);
    }

    public function vnpayReturn(Request $request): JsonResponse
    {
        $query = $request->query();

        Log::debug('VNPAY return query', $query);

        $missingParams = $this->missingVnpayReturnParams($query);

        if ($missingParams !== []) {
            if ($paidResponse = $this->paidVnpayReturnResponseFromTxnRef($query, 'missing_params')) {
                return $paidResponse;
            }

            return response()->json([
                'success' => false,
                'message' => 'Thieu tham so VNPAY return',
                'errors' => [
                    'missing' => $missingParams,
                ],
                'data' => [
                    'received_fields' => array_keys($query),
                ],
            ]);
        }

        try {
            $result = $this->vnpayService->handleReturn($query);
        } catch (Throwable $exception) {
            Log::error('vnpay.return_failed', [
                'request_fields' => array_keys($query),
                'message' => $exception->getMessage(),
            ]);

            if ($paidResponse = $this->paidVnpayReturnResponseFromTxnRef($query, 'exception')) {
                return $paidResponse;
            }

            return response()->json([
                'success' => false,
                'message' => 'Loi xu ly VNPAY return',
                'error' => $exception->getMessage(),
            ]);
        }

        if (! $result['verified']) {
            if ($paidResponse = $this->paidVnpayReturnResponseFromTxnRef($query, 'invalid_signature')) {
                return $paidResponse;
            }

            return response()->json([
                'success' => false,
                'message' => 'Chu ky VNPAY khong hop le',
            ]);
        }

        if ($result['successful'] || ($result['payment_status'] ?? null) === 'paid') {
            $payment = $result['payment'] ?? [];

            return response()->json([
                'success' => true,
                'message' => 'Thanh toán thành công',
                'data' => [
                    'verified' => true,
                    'successful' => true,
                    'payment_status' => 'paid',
                    'payment_id' => $payment['id'] ?? null,
                    'order_id' => $payment['order_id'] ?? null,
                    'amount' => $payment['amount'] ?? null,
                    'order_updated' => (bool) ($result['order_updated'] ?? false),
                    'order_update' => $result['order_update'] ?? null,
                    'transaction_code' => $payment['transaction_code'] ?? null,
                    'cart_clear' => $result['cart_clear'] ?? null,
                ],
            ]);
        }

        if ($paidResponse = $this->paidVnpayReturnResponseFromTxnRef($query, 'failed_callback_after_paid')) {
            return $paidResponse;
        }

        return response()->json([
            'success' => false,
            'message' => 'Thanh toán thất bại',
            'data' => [
                'verified' => true,
                'successful' => false,
                'payment_status' => $result['payment_status'],
            ],
        ]);
    }

    public function vnpayIpn(Request $request): JsonResponse
    {
        Log::debug('VNPAY IPN query', $request->query());

        try {
            $result = $this->vnpayService->handleIpn($request->query());
        } catch (Throwable $exception) {
            $response = ['RspCode' => '99', 'Message' => 'Unknown error'];

            Log::error('vnpay.ipn_failed', [
                'request_fields' => array_keys($request->query()),
                'response' => $response,
                'message' => $exception->getMessage(),
            ]);

            return response()->json($response);
        }

        return response()->json($result['response']);
    }

    private function clientIp(Request $request): string
    {
        $forwardedFor = $request->headers->get('X-Forwarded-For');

        if ($forwardedFor) {
            return trim(explode(',', $forwardedFor)[0]);
        }

        return $request->ip() ?: '127.0.0.1';
    }

    private function logVnpayCreateAuthContext(Request $request): void
    {
        $routeMiddleware = $request->route()?->gatherMiddleware() ?? [];
        $authorization = $request->header('Authorization');
        $bearerToken = $request->bearerToken();

        Log::debug('VNPAY create auth context', [
            'route_middleware' => $routeMiddleware,
            'requires_auth' => collect($routeMiddleware)->contains(
                fn (string $middleware) => str_contains($middleware, 'auth') || str_contains($middleware, 'token')
            ),
            'auth_user' => $request->attributes->get('auth_user'),
            'authorization_present' => $authorization !== null,
            'bearer_token_prefix' => $bearerToken ? substr($bearerToken, 0, 12) : null,
        ]);
    }

    private function vnpayCreateValidationError(Request $request, array $errors): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => 'Payload tao thanh toan VNPAY khong hop le',
            'errors' => $errors,
            'data' => [
                'errors' => $errors,
                'required_fields' => $this->vnpayCreateRequiredFields(),
                'received_fields' => array_keys($request->all()),
            ],
        ];

        Log::warning('VNPAY create validation failed', [
            'received_fields' => array_keys($request->all()),
            'errors' => $errors,
        ]);

        return response()->json($response, 422);
    }

    private function vnpayCreateRequiredFields(): array
    {
        return [
            'order_id' => 'required integer',
            'amount' => 'required numeric integer min:1000',
            'order_info' => 'nullable string max:255',
        ];
    }

    private function missingVnpayReturnParams(array $query): array
    {
        return collect([
            'vnp_Amount',
            'vnp_ResponseCode',
            'vnp_TmnCode',
            'vnp_TransactionStatus',
            'vnp_TxnRef',
            'vnp_SecureHash',
        ])
            ->filter(fn (string $param) => ! array_key_exists($param, $query) || $query[$param] === '')
            ->values()
            ->all();
    }

    private function paidVnpayReturnResponseFromTxnRef(array $query, string $reason): ?JsonResponse
    {
        $txnRef = (string) ($query['vnp_TxnRef'] ?? '');

        if ($txnRef === '') {
            return null;
        }

        $payment = $this->paymentService->paymentForVnpayTxnRef($txnRef);

        if (! $payment || $payment->status !== 'paid') {
            return null;
        }

        $orderUpdate = $this->orderServiceClient->markPaymentPaid((int) $payment->order_id);

        Log::info('vnpay.return.already_paid_response', [
            'reason' => $reason,
            'txn_ref' => $txnRef,
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'payment_status' => $payment->status,
            'order_service_url' => config('services.order.url'),
            'order_update_status' => $orderUpdate['status'] ?? null,
            'order_updated' => $orderUpdate['updated'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thanh toán thành công',
            'data' => [
                'verified' => true,
                'successful' => true,
                'payment_status' => 'paid',
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'order_updated' => (bool) ($orderUpdate['updated'] ?? false),
                'order_update' => $orderUpdate,
                'transaction_code' => $payment->transaction_code,
            ],
        ]);
    }
}
