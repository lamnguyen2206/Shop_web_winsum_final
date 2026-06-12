<?php require_once __DIR__ . '/helpers.php'; ?>
<link rel="stylesheet" href="assets/css/footer.css">
<footer class="footer">
    <div class="footer-container">
        <div class="footer-grid">
            <div class="footer-col footer-col--brand">
                <div class="footer-logo">
                    <img src="assets/images/logo-footer.webp" alt="Winsum Home">
                </div>
                <div class="footer-info">
                    <div class="footer-item">
                        <span aria-hidden="true">📍</span>
                        <p>Địa chỉ: Hoàng Mai, Hà Nội</p>
                    </div>
                    <div class="footer-item">
                        <span aria-hidden="true">📱</span>
                        <p>Số điện thoại: 0387239676</p>
                    </div>
                    <div class="footer-item">
                        <span aria-hidden="true">✉️</span>
                        <p>Email: winsum.decor@gmail.com</p>
                    </div>
                </div>
                <p class="footer-copy">
                    © Bản quyền thuộc về
                    <a href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener">Winsum Home</a> |
                    <a href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener">Thiết kế bởi WS</a>
                </p>
            </div>

            <nav class="footer-col" aria-label="Hỗ trợ khách hàng">
                <h4 class="footer-col__title">Hỗ trợ khách hàng</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo e(app_url('catalog')); ?>">Tìm kiếm sản phẩm</a></li>
                    <li><a href="<?php echo e(app_url('order-lookup')); ?>">Tra cứu đơn hàng</a></li>
                    <li><a href="<?php echo e(app_url('blog')); ?>">Hướng dẫn &amp; mẹo decor</a></li>
                    <li><a href="<?php echo e(app_url('account')); ?>">Tài khoản của tôi</a></li>
                </ul>
            </nav>

            <nav class="footer-col" aria-label="Chính sách">
                <h4 class="footer-col__title">Chính sách</h4>
                <ul class="footer-links">
                    <li><a href="<?php echo e(app_url('home')); ?>">Chính sách giao hàng</a></li>
                    <li><a href="<?php echo e(app_url('home')); ?>">Chính sách thanh toán</a></li>
                    <li><a href="<?php echo e(app_url('home')); ?>">Chính sách bảo mật</a></li>
                    <li><a href="<?php echo e(app_url('home')); ?>">Chính sách đổi trả</a></li>
                </ul>
            </nav>

            <div class="footer-col">
                <h4 class="footer-col__title">Đăng ký nhận tin</h4>
                <p class="footer-col__note">Bạn muốn nhận khuyến mãi đặc biệt? Đăng ký ngay.</p>
                <form class="footer-newsletter" action="#" onsubmit="return false;">
                    <label class="visually-hidden" for="footer-newsletter-email">Email nhận tin</label>
                    <input id="footer-newsletter-email" type="email" placeholder="Nhập địa chỉ email" autocomplete="email">
                    <button type="submit">Đăng ký</button>
                </form>
                <div class="footer-social">
                    <a href="https://www.facebook.com/" target="_blank" rel="noopener" aria-label="Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13 22v-8h3l.5-4H13V7.6c0-1.2.3-2 2-2H17V2.2C16.6 2.1 15.4 2 14.1 2 11.1 2 9 3.8 9 7.2V10H6v4h3v8h4z"/></svg>
                    </a>
                    <a href="https://www.instagram.com/winsum.homedecor/?hl=en" target="_blank" rel="noopener" aria-label="Instagram">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.2" cy="6.8" r="1.1" fill="currentColor" stroke="none"/></svg>
                    </a>
                    <a href="https://www.tiktok.com/" target="_blank" rel="noopener" aria-label="TikTok">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.2 3c.4 2.3 1.9 3.9 4.2 4.2v3c-1.6 0-3.1-.5-4.2-1.3v6.4c0 3.5-2.8 6.3-6.3 6.3S3.6 18.8 3.6 15.3 6.4 9 9.9 9c.5 0 .9 0 1.4.1v3.2c-.4-.1-.9-.2-1.4-.2-1.8 0-3.2 1.4-3.2 3.2s1.4 3.2 3.2 3.2 3.2-1.4 3.2-3.2V3h3.1z"/></svg>
                    </a>
                </div>
            </div>
        </div>

        <div class="footer-bottom">
            <p class="footer-bottom__label">Phương thức thanh toán</p>
            <div class="footer-payments" aria-label="Phương thức thanh toán">
                <span class="footer-payment footer-payment--visa">VISA</span>
                <span class="footer-payment footer-payment--master">MasterCard</span>
                <span class="footer-payment footer-payment--momo">MoMo</span>
                <span class="footer-payment footer-payment--zalo">ZaloPay</span>
                <span class="footer-payment footer-payment--cod">COD</span>
            </div>
        </div>
    </div>
</footer>
