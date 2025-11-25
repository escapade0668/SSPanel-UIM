<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Product;
use App\Services\Coupon;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class CouponController extends BaseController
{
    public function check(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $couponCode = $this->antiXss->xss_clean($request->getParam('coupon'));
        $productId = $this->antiXss->xss_clean($request->getParam('product_id'));

        $product = (new Product())->where('id', $productId)->first();

        if ($product === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '优惠码无效',
            ]);
        }

        $couponService = new Coupon();

        if (! $couponService->validate($couponCode, $product, $this->user)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => $couponService->getError(),
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '优惠码可用',
            'data' => [
                'coupon-code' => $couponService->getCoupon()->code,
                'product-buy-discount' => $couponService->getDiscount(),
                'product-buy-total' => $couponService->getFinalPrice(),
            ],
        ]);
    }
}
