<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <title>Thông báo đơn hàng BStore</title>
</head>
<body style="margin:0;background:#f4f7fb;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
    <div style="max-width:680px;margin:0 auto;padding:28px 16px;">
        <div style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;padding:24px;">
            <h1 style="margin:0 0 12px;font-size:22px;color:#111827;">
                @if ($eventType === 'status_updated')
                    Cập nhật trạng thái đơn hàng
                @else
                    BStore đã nhận đơn hàng của bạn
                @endif
            </h1>

            <p style="margin:0 0 18px;line-height:1.6;">Thông tin đơn hàng của bạn được cập nhật như sau:</p>

            <table style="width:100%;border-collapse:collapse;margin-bottom:20px;">
                <tr>
                    <td style="padding:8px 0;color:#6b7280;width:150px;">Mã đơn hàng</td>
                    <td style="padding:8px 0;font-weight:700;">{{ $order['order_code'] }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#6b7280;">Trạng thái</td>
                    <td style="padding:8px 0;font-weight:700;">{{ $order['status_label'] ?? $order['status'] }}</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#6b7280;">Tổng tiền</td>
                    <td style="padding:8px 0;font-weight:700;">{{ number_format((float) $order['total_amount'], 0, ',', '.') }} đ</td>
                </tr>
                <tr>
                    <td style="padding:8px 0;color:#6b7280;">Ngày đặt hàng</td>
                    <td style="padding:8px 0;">{{ optional($order['created_at'])->format('d/m/Y H:i') }}</td>
                </tr>
            </table>

            <h2 style="margin:0 0 10px;font-size:17px;color:#111827;">Sản phẩm</h2>
            <table style="width:100%;border-collapse:collapse;border:1px solid #e5e7eb;">
                <thead>
                    <tr style="background:#f9fafb;">
                        <th align="left" style="padding:10px;border-bottom:1px solid #e5e7eb;">Tên sản phẩm</th>
                        <th align="center" style="padding:10px;border-bottom:1px solid #e5e7eb;">SL</th>
                        <th align="right" style="padding:10px;border-bottom:1px solid #e5e7eb;">Đơn giá</th>
                        <th align="right" style="padding:10px;border-bottom:1px solid #e5e7eb;">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($order['items'] as $item)
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #f3f4f6;">
                                <strong>{{ $item['product_name'] }}</strong>
                                <div style="font-size:12px;color:#6b7280;">
                                    {{ collect([$item['color'], $item['ram'], $item['storage']])->filter()->implode(' / ') }}
                                </div>
                            </td>
                            <td align="center" style="padding:10px;border-bottom:1px solid #f3f4f6;">{{ $item['quantity'] }}</td>
                            <td align="right" style="padding:10px;border-bottom:1px solid #f3f4f6;">{{ number_format((float) $item['price'], 0, ',', '.') }} đ</td>
                            <td align="right" style="padding:10px;border-bottom:1px solid #f3f4f6;">{{ number_format((float) $item['subtotal'], 0, ',', '.') }} đ</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="padding:12px;text-align:center;color:#6b7280;">Chưa có thông tin sản phẩm.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <p style="margin:20px 0 0;color:#6b7280;font-size:13px;">Cảm ơn bạn đã mua sắm tại BStore.</p>
        </div>
    </div>
</body>
</html>
