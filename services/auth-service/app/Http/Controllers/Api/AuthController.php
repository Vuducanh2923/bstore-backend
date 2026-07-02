<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
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
    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->authService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Dang ky thanh cong. Vui long kiem tra email de nhap ma OTP xac thuc.',
            'data' => $user,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->authService->login($data['email'], $data['password']);

        if ($result['status'] === 'email_unverified') {
            return response()->json([
                'success' => false,
                'message' => 'Vui long xac thuc email truoc khi dang nhap',
                'data' => null,
            ], 403);
        }

        if (! $result['user']) {
            return response()->json([
                'success' => false,
                'message' => 'Email hoac mat khau khong dung',
                'data' => null,
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Dang nhap thanh cong',
            'data' => $result['user'],
        ]);
    }

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
            'message' => 'Xac thuc email thanh cong',
            'data' => $result['user'],
        ]);
    }

    public function resendRegisterOtp(ResendRegisterOtpRequest $request): JsonResponse
    {
        $status = $this->authService->resendRegisterOtp($request->validated('email'), $request->ip());

        if ($status === EmailVerificationService::STATUS_THROTTLED) {
            return $this->tooManyOtpAttemptsResponse();
        }

        return response()->json([
            'success' => true,
            'message' => 'Neu email hop le, ma OTP moi da duoc gui.',
            'data' => null,
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        try {
            $status = $this->authService->requestForgotPasswordOtp($request->validated('email'), $request->ip());
        } catch (Throwable $exception) {
            $message = app()->environment('local')
                ? 'Khong gui duoc email OTP: '.$exception->getMessage()
                : 'Khong gui duoc email OTP. Vui long thu lai sau.';

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
                'success' => false,
                'message' => 'Email khong ton tai trong bang users',
                'data' => null,
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Neu email hop le, ma OTP dat lai mat khau da duoc gui.',
            'data' => null,
        ]);
    }

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
            Mail::raw('Email test SMTP tu BStore Auth Service.', function ($message) use ($email) {
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
                'message' => 'Khong gui duoc email test: '.$exception->getMessage(),
                'data' => null,
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Email test da duoc gui',
            'data' => null,
        ]);
    }

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
            'message' => 'Xac thuc OTP thanh cong',
            'data' => null,
        ]);
    }

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
            'message' => 'Dat lai mat khau thanh cong',
            'data' => null,
        ]);
    }

    private function invalidOtpResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ma OTP khong hop le hoac da het han',
            'data' => null,
        ], 422);
    }

    private function tooManyOtpAttemptsResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Ban thao tac qua nhanh. Vui long thu lai sau.',
            'data' => null,
        ], 429);
    }
}
