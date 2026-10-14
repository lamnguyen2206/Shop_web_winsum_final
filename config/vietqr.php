<?php

declare(strict_types=1);

/**
 * Tài khoản nhận chuyển khoản VietQR.
 * Điền số tài khoản thật của shop. Mã ngân hàng là tên viết tắt VietQR:
 * MB, VCB, ACB, TCB, BIDV, VPB, TPB, STB, MSB, OCB.
 * Có thể ghi đè bằng biến môi trường VIETQR_BANK, VIETQR_ACCOUNT, VIETQR_ACCOUNT_NAME.
 */
return [
    'bank_code' => getenv('VIETQR_BANK') ?: 'MB',
    'account_number' => getenv('VIETQR_ACCOUNT') ?: '',
    'account_name' => getenv('VIETQR_ACCOUNT_NAME') ?: 'WINSUM HOME',
];
