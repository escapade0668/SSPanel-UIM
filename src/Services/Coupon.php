<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use function explode;
use function in_array;
use function json_decode;
use function max;
use function property_exists;
use function time;

final class Coupon
{
    private ?UserCoupon $coupon = null;
    private ?Product $product = null;
    private ?User $user = null;
    private string $error = '';
    private float $discount = 0;

    /**
     * 验证优惠码
     *
     * @param string $couponCode 优惠码
     * @param Product $product 商品
     * @param User $user 用户
     *
     * @return bool 验证是否通过
     */
    public function validate(string $couponCode, Product $product, User $user): bool
    {
        $this->product = $product;
        $this->user = $user;
        $this->error = '';
        $this->discount = 0;

        // 检查优惠码是否为空
        if ($couponCode === '') {
            $this->error = '优惠码无效';
            return false;
        }

        // 检查优惠码是否存在
        $this->coupon = (new UserCoupon())->where('code', $couponCode)->first();

        if ($this->coupon === null) {
            $this->error = '优惠码不存在';
            return false;
        }

        // 检查是否过期
        if ($this->coupon->expire_time !== 0 && $this->coupon->expire_time < time()) {
            $this->error = '优惠码已过期';
            return false;
        }

        $limit = json_decode($this->coupon->limit);

        // 检查是否被禁用
        if ($limit->disabled) {
            $this->error = '优惠码已被禁用';
            return false;
        }

        // 检查是否适用于当前商品
        if ($limit->product_id !== '' && ! in_array((string) $product->id, explode(',', $limit->product_id))) {
            $this->error = '优惠码不适用于此商品';
            return false;
        }

        // 检查新用户限制
        if (property_exists($limit, 'new_user') && (int) $limit->new_user === 1) {
            $userOrderCount = (new Order())->where('user_id', $user->id)->count();
            if ($userOrderCount > 0) {
                $this->error = '此优惠码仅限新用户使用';
                return false;
            }
        }

        // 检查单用户使用次数限制
        $useLimit = (int) $limit->use_time;

        if ($useLimit > 0) {
            $userUseCount = (new Order())
                ->where('user_id', $user->id)
                ->where('coupon', $this->coupon->code)
                ->count();

            if ($userUseCount >= $useLimit) {
                $this->error = '优惠码使用次数已达上限';
                return false;
            }
        }

        // 检查总使用次数限制
        $totalUseLimit = property_exists($limit, 'total_use_time') ? (int) $limit->total_use_time : -1;

        if ($totalUseLimit > 0 && $this->coupon->use_count >= $totalUseLimit) {
            $this->error = '优惠码使用次数已达上限';
            return false;
        }

        // 计算折扣金额
        $this->calculateDiscount();

        return true;
    }

    /**
     * 计算折扣金额
     */
    private function calculateDiscount(): void
    {
        $content = json_decode($this->coupon->content);

        if ($content->type === 'percentage') {
            // 百分比折扣
            $this->discount = $this->product->price * $content->value / 100;
        } else {
            // 固定金额折扣
            $this->discount = (float) $content->value;
        }

        // 确保折扣不超过商品价格（防止负数价格）
        $this->discount = max(0, min($this->discount, $this->product->price));
    }

    /**
     * 获取错误信息
     */
    public function getError(): string
    {
        return $this->error;
    }

    /**
     * 获取折扣金额
     */
    public function getDiscount(): float
    {
        return $this->discount;
    }

    /**
     * 获取折后价格
     */
    public function getFinalPrice(): float
    {
        return max(0, $this->product->price - $this->discount);
    }

    /**
     * 获取优惠码实例
     */
    public function getCoupon(): ?UserCoupon
    {
        return $this->coupon;
    }

    /**
     * 增加优惠码使用次数
     */
    public function incrementUseCount(): void
    {
        if ($this->coupon !== null) {
            $this->coupon->use_count += 1;
            $this->coupon->save();
        }
    }
}

