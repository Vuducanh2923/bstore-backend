<?php

use App\Support\ProductDescriptionSanitizer;

test('product description sanitizer preserves safe CKEditor formatting', function () {
    $html = '<h2>Thong tin</h2><p><strong>Noi bat</strong><br><em>Chi tiet</em></p>'
        .'<ul><li>Muc 1</li><li><u>Muc 2</u></li></ul>'
        .'<blockquote>Ghi chu</blockquote><p><a href="https://example.com/product?q=1">Xem them</a></p>';

    $sanitized = (new ProductDescriptionSanitizer)->sanitize($html);

    expect($sanitized)
        ->toContain('<h2>Thong tin</h2>')
        ->toContain('<strong>Noi bat</strong>')
        ->toContain('<ul><li>Muc 1</li><li><u>Muc 2</u></li></ul>')
        ->toContain('<a href="https://example.com/product?q=1">Xem them</a>');
});

test('product description sanitizer removes stored XSS vectors', function () {
    $html = '<p onclick="alert(1)">Safe<script>alert(2)</script></p>'
        .'<iframe src="https://evil.test"></iframe><svg onload="alert(3)"><circle /></svg>'
        .'<a href="javascript:alert(4)" onmouseover="alert(5)">Bad</a>'
        .'<a href="data:text/html;base64,abc">Data</a><span>kept text</span>';

    $sanitized = (new ProductDescriptionSanitizer)->sanitize($html);

    expect(strtolower($sanitized))
        ->not->toContain('<script')
        ->not->toContain('<iframe')
        ->not->toContain('<svg')
        ->not->toContain('onclick')
        ->not->toContain('onmouseover')
        ->not->toContain('javascript:')
        ->not->toContain('data:')
        ->and($sanitized)->toContain('<p>Safe</p>')
        ->toContain('<a>Bad</a>')
        ->toContain('kept text');
});
