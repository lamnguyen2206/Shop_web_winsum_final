<?php

/**

 * Partial: modal chọn voucher (tabs + thẻ ngang).

 * Expects: $couponSuggestions (array), optional $couponAppliedCode (string)

 */

if (!isset($couponSuggestions) || !is_array($couponSuggestions)) {

    return;

}



$visibleSuggestions = array_values(array_filter(

    $couponSuggestions,

    static function (array $s): bool {

        return !empty($s['is_applied'])

            || !empty($s['can_apply'])

            || !empty($s['is_locked'])

            || ((int) ($s['shortfall'] ?? 0) > 0);

    }

));



if ($visibleSuggestions === []) {

    return;

}



$appliedCode = isset($couponAppliedCode)

    ? strtoupper(trim((string) $couponAppliedCode))

    : couponGetAppliedCode();

$hasApplied = $appliedCode !== '';



$eligibleCount = 0;

foreach ($visibleSuggestions as $s) {

    if (!empty($s['can_apply']) && empty($s['is_applied'])) {

        $eligibleCount++;

    }

}



$suggestionCount = count($visibleSuggestions);

$catalogUrl = app_url('catalog');
?>



<div class="coupon-modal" id="coupon-modal" data-coupon-modal hidden aria-hidden="true">

    <div class="coupon-modal__backdrop" data-coupon-modal-close tabindex="-1" aria-hidden="true"></div>

    <div class="coupon-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="coupon-modal-title">

        <header class="coupon-modal__header">

            <div>

                <h2 class="coupon-modal__title" id="coupon-modal-title">Chọn mã giảm giá</h2>

                <p class="coupon-modal__subtitle">

                    <?php if ($hasApplied): ?>

                        Đang dùng <strong><?php echo e($appliedCode); ?></strong>

                        <?php if ($eligibleCount > 0): ?>

                            · <?php echo (int) $eligibleCount; ?> mã khác khả dụng

                        <?php endif; ?>

                    <?php else: ?>

                        <?php echo (int) $suggestionCount; ?> ưu đãi · Mỗi đơn một mã

                    <?php endif; ?>

                </p>

            </div>

            <button type="button" class="coupon-modal__close" data-coupon-modal-close aria-label="Đóng">

                <span aria-hidden="true">&times;</span>

            </button>

        </header>



        <div class="coupon-modal__body" data-coupon-panel>

            <p class="coupon-suggestions__hint">Hệ thống tự chọn mã tiết kiệm nhất khi vào giỏ hàng (nếu chưa áp mã).</p>



            <div class="coupon-tabs" role="tablist" aria-label="Lọc voucher">

                <button type="button" class="coupon-tabs__btn is-active" role="tab" aria-selected="true" data-coupon-tab="all">Tất cả</button>

                <button type="button" class="coupon-tabs__btn" role="tab" aria-selected="false" data-coupon-tab="discount">Mã giảm giá</button>

                <button type="button" class="coupon-tabs__btn" role="tab" aria-selected="false" data-coupon-tab="shipping">Freeship</button>

                <button type="button" class="coupon-tabs__btn" role="tab" aria-selected="false" data-coupon-tab="vip">VIP</button>

            </div>



            <ul class="voucher-list">

                <?php foreach ($visibleSuggestions as $suggestion): ?>

                    <?php

                    $isApplied = !empty($suggestion['is_applied']);

                    $canApply = !empty($suggestion['can_apply']);

                    $isLocked = !empty($suggestion['is_locked']);

                    $shortfall = (int) ($suggestion['shortfall'] ?? 0);

                    $progress = (int) ($suggestion['progress_percent'] ?? 0);

                    $tab = (string) ($suggestion['coupon_role'] ?? $suggestion['tab'] ?? 'discount');

                    $allowApply = $canApply && (!$hasApplied || $isApplied || strtoupper((string) $suggestion['code']) !== $appliedCode);

                    $cardClass = 'voucher-card';

                    if ($isApplied) {

                        $cardClass .= ' voucher-card--applied';

                    } elseif ($canApply) {

                        $cardClass .= ' voucher-card--eligible';

                    } else {

                        $cardClass .= ' voucher-card--locked';

                    }

                    ?>

                    <li class="<?php echo e($cardClass); ?>" data-coupon-category="<?php echo e($tab); ?>">

                        <div class="voucher-card__inner">

                            <div class="voucher-card__body">

                                <div class="voucher-card__headline">

                                    <span class="voucher-card__amount"><?php echo e($suggestion['display_amount']); ?></span>

                                    <span class="voucher-card__code"><?php echo e($suggestion['code']); ?></span>

                                    <span class="voucher-card__role voucher-card__role--<?php echo e($suggestion['coupon_role'] ?? $tab); ?>"><?php echo e($suggestion['role_label'] ?? ''); ?></span>

                                </div>

                                <p class="voucher-card__summary"><?php echo e($suggestion['short_summary']); ?></p>

                                <p class="voucher-card__expiry"><?php echo e($suggestion['expiry_hint']); ?></p>



                                <?php if (!empty($suggestion['urgency_badge'])): ?>

                                    <span class="voucher-card__urgency"><?php echo e($suggestion['urgency_badge']); ?></span>

                                <?php endif; ?>



                                <?php if ($isLocked && $shortfall > 0): ?>

                                    <div class="voucher-card__progress-wrap">

                                        <div class="voucher-card__progress-track">

                                            <div class="voucher-card__progress-bar" style="width: <?php echo max(4, $progress); ?>%;"></div>

                                        </div>

                                        <p class="voucher-card__unlock">Mua thêm <?php echo number_format($shortfall, 0, ',', '.'); ?>đ để mở khóa</p>

                                        <a class="voucher-card__shop-more" href="<?php echo e($catalogUrl); ?>">Mua thêm để nhận ưu đãi</a>

                                    </div>

                                <?php elseif (!$canApply && !$isApplied): ?>

                                    <p class="voucher-card__note"><?php echo e($suggestion['message']); ?></p>

                                <?php endif; ?>



                                <?php if (!empty($suggestion['detail_text'])): ?>

                                    <details class="voucher-card__details">

                                        <summary>Điều kiện</summary>

                                        <p><?php echo e($suggestion['detail_text']); ?></p>

                                    </details>

                                <?php endif; ?>

                            </div>



                            <div class="voucher-card__action">

                                <?php if ($isApplied): ?>

                                    <span class="voucher-card__status">Đang áp dụng</span>

                                    <form method="post" action="<?php echo e(app_url('cart')); ?>" class="voucher-card__form">

                                        <?php echo csrfField(); ?>

                                        <input type="hidden" name="action" value="apply_coupon">

                                        <input type="hidden" name="coupon_code" value="">

                                        <button type="submit" class="voucher-card__btn voucher-card__btn--ghost">Bỏ mã</button>

                                    </form>

                                <?php elseif ($allowApply): ?>

                                    <form method="post" action="<?php echo e(app_url('cart')); ?>" class="voucher-card__form">

                                        <?php echo csrfField(); ?>

                                        <input type="hidden" name="action" value="apply_coupon">

                                        <input type="hidden" name="coupon_code" value="<?php echo e($suggestion['code']); ?>">

                                        <button type="submit" class="voucher-card__btn">

                                            <?php echo $hasApplied ? 'Đổi' : 'Áp dụng'; ?>

                                        </button>

                                    </form>

                                <?php else: ?>

                                    <span class="voucher-card__status voucher-card__status--muted">Chưa đủ ĐK</span>

                                <?php endif; ?>

                            </div>

                        </div>

                    </li>

                <?php endforeach; ?>

            </ul>



            <p class="coupon-suggestions__empty is-hidden" data-coupon-empty hidden>Không có voucher trong tab này.</p>

        </div>

    </div>

</div>


