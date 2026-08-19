<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\LogoutRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResendRegisterOtpRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyForgotPasswordOtpRequest;
use App\Http\Requests\Auth\VerifyRegisterOtpRequest;
use App\Services\AuthService;
use App\Services\EmailVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AuthController extends Controller
{

    // Khởi tạo đối tượng và các phụ thuộc cần thiết.
    public function __construct(private readonly AuthService $authService) {}

    // Tạo hoặc lưu dữ liệu theo nghiệp vụ của hàm.
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để nhập mã OTP xác thực.',
            'data' => $user,
        ], 201);
    }

    // Thực hiện đăng nhập.
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->login($data['email'], $data['password']);

        if ($result['status'] === 'password_reset_required') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản sử dụng mật khẩu cũ. Vui lòng đặt lại mật khẩu.',
                'data' => null,
            ], 403);
        }

        if ($result['status'] === 'email_unverified') {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng xác thực email trước khi đăng nhập',
                'data' => null,
            ], 403);
        }

        if ($result['status'] === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản đã bị vô hiệu hóa',
                'data' => null,
            ], 403);
        }

        if (! $result['user']) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng',
                'data' => null,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đăng nhập thành công',
            'data' => $result['user'],
        ]);
    }

    // Làm mới hoặc đặt lại dữ liệu theo nghiệp vụ của hàm.
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        $result = $this->authService->refresh($request->validated('refresh_token'));

        if ($result['status'] === 'inactive') {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản đã bị vô hiệu hóa',
                'data' => null,
            ], 403);
        }

        if (! $result['user']) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token không hợp lệ hoặc đã hết hạn',
                'data' => null,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Làm mới phiên đăng nhập thành công',
            'data' => $result['user'],
        ]);
    }

    // Thực hiện đăng xuất.
    public function logout(LogoutRequest $request): JsonResponse
    {
        $revoked = $this->authService->logout(
            $request->bearerToken(),
            $request->validated('refresh_token'),
        );

        if (! $revoked) {
            return response()->json([
                'success' => false,
                'message' => 'Phiên đăng nhập không hợp lệ hoặc đã bị thu hồi',
                'data' => null,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Đăng xuất thành công',
            'data' => null,
        ]);
    }

    // Thực hiện xác minh đăng ký otp.
    public function verifyRegisterOtp(VerifyRegisterOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->verifyRegisterOtp($data['email'], $data['otp_code'], $request->ip());

        if ($result['status'] === EmailVerificationService::STATUS_THROTTLED) {
            return $this->tooManyOtpAttemptsResponse();
        }

        if (! $result['user']) {
            return $this->invalidOtpResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Xác thực email thành công',
            'data' => $result['user'],
        ]);
    }

    // Thực hiện resend đăng ký otp.
    public function resendRegisterOtp(ResendRegisterOtpRequest $request): JsonResponse
    {
        $status = $this->authService->resendRegisterOtp($request->validated('email'), $request->ip());

        if ($status === EmailVerificationService::STATUS_THROTTLED) {
            return $this->tooManyOtpAttemptsResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Nếu email hợp lệ, mã OTP mới đã được gửi.',
            'data' => null,
        ]);
    }

    // Thực hiện forgot mật khẩu.
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authService->requestForgotPasswordOtp($request->validated('email'), $request->ip());
        } catch (Throwable $exception) {
            $message = app()->environment('local')
                ? 'Không gửi được email OTP: '.$exception->getMessage()
                : 'Không gửi được email OTP. Vui lòng thử lại sau.';

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null,
            ], 500);
        }

        if ($status === EmailVerificationService::STATUS_THROTTLED) {
            return $this->tooManyOtpAttemptsResponse();
        }

        if ($status === EmailVerificationService::STATUS_EMAIL_NOT_FOUND) {
            return response()->json([
                'success' => true,
                'message' => 'Nếu email hợp lệ, mã OTP đặt lại mật khẩu đã được gửi.',
                'data' => null,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Nếu email hợp lệ, mã OTP đặt lại mật khẩu đã được gửi.',
            'data' => null,
        ]);
    }

    // Thực hiện debug send mail.
    public function debugSendMail(Request $request): JsonResponse
    {
        if (! app()->environment('local')) {
            abort(404);
        }

        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
        ]);

        $email = strtolower(trim($data['email']));

        Log::info('Sending debug email', ['email' => $email]);

        try {
            Mail::raw('Email kiểm thử SMTP từ Dịch vụ xác thực BStore.', function ($message) use ($email) {
                $message->to($email)->subject('BStore SMTP test');
            });

            Log::info('Debug email sent', ['email' => $email]);
        } catch (Throwable $exception) {
            Log::error('Debug email failed', [
                'email' => $email,
                'error' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Không gửi được email kiểm thử: '.$exception->getMessage(),
                'data' => null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email kiểm thử đã được gửi',
            'data' => null,
        ]);
    }

    // Thực hiện xác minh forgot mật khẩu otp.
    public function verifyForgotPasswordOtp(VerifyForgotPasswordOtpRequest $request): JsonResponse
    {
        $data = $request->validated();
        $status = $this->authService->verifyForgotPasswordOtp($data['email'], $data['otp_code'], $request->ip());

        if ($status === EmailVerificationService::STATUS_THROTTLED) {
            return $this->tooManyOtpAttemptsResponse();
        }

        if ($status !== EmailVerificationService::STATUS_VERIFIED) {
            return $this->invalidOtpResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Xác thực OTP thành công',
            'data' => null,
        ]);
    }

    // Làm mới hoặc đặt lại mật khẩu.
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $status = $this->authService->resetPassword($data['email'], $data['otp_code'], $data['password'], $request->ip());

        if ($status === EmailVerificationService::STATUS_THROTTLED) {
            return $this->tooManyOtpAttemptsResponse();
        }

        if ($status !== EmailVerificationService::STATUS_VERIFIED) {
            return $this->invalidOtpResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Đặt lại mật khẩu thành công',
            'data' => null,
        ]);
    }

    // Thực hiện invalid otp phản hồi.
    private function invalidOtpResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Mã OTP không hợp lệ hoặc đã hết hạn',
            'data' => null,
        ], 422);
    }

    // Thực hiện too many otp attempts phản hồi.
    private function tooManyOtpAttemptsResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ban thao tác qua nhanh. Vui lòng thu lai sau.',
            'data' => null,
        ], 429);
    }
}
