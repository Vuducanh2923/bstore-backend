<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Mã xác thực đăng ký BStore</title>
</head>
<body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 12px;font-size:22px;color:#111827;">Xác thực email BStore</h1>
            <p style="margin:0 0 16px;line-height:1.6;">Cảm ơn bạn đã đăng ký tài khoản BStore. Vui lòng dùng mã OTP bên dưới để hoàn tất xác thực email.</p>
            <div style="margin:22px 0;padding:18px;border-radius:8px;background:#eef6ff;text-align:center;">
                <div style="font-size:13px;color:#4b5563;margin-bottom:8px;">Mã OTP của bạn</div>
                <div style="font-size:34px;letter-spacing:6px;font-weight:700;color:#0f172a;">{{ $otpCode }}</div>
            </div>
            <p style="margin:0 0 12px;line-height:1.6;">Mã này có hiệu lực trong {{ $expiresInMinutes }} phút. Không chia sẻ mã này cho bất kỳ ai.</p>
            <p style="margin:20px 0 0;color:#6b7280;font-size:13px;">Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email.</p>
        </div>
    </div>
</body>
</html>
