<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Mã OTP đặt lại mật khẩu BStore</title>
</head>
<body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:560px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 12px;font-size:22px;color:#111827;">Đặt lại mật khẩu BStore</h1>
            <p style="margin:0 0 16px;line-height:1.6;">BStore đã nhận yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Hãy dùng mã OTP sau để xác thực yêu cầu.</p>
            <div style="margin:22px 0;padding:18px;border-radius:8px;background:#fff7ed;text-align:center;">
                <div style="font-size:13px;color:#4b5563;margin-bottom:8px;">Mã OTP đặt lại mật khẩu</div>
                <div style="font-size:34px;letter-spacing:6px;font-weight:700;color:#9a3412;">{{ $otpCode }}</div>
            </div>
            <p style="margin:0 0 12px;line-height:1.6;">Mã này có hiệu lực trong {{ $expiresInMinutes }} phút. Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email.</p>
            <p style="margin:20px 0 0;color:#6b7280;font-size:13px;">BStore không bao giờ yêu cầu bạn cung cấp mật khẩu qua email.</p>
        </div>
    </div>
</body>
</html>
