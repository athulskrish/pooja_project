<?php
declare(strict_types=1);

/**
 * OrderController - Manages Cart Calculation, Checkout Transactions, and History
 * MCSP-232: E-Commerce Platform for Food Order
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Address.php';
require_once __DIR__ . '/../models/AprioriEngine.php';

class OrderController {
    public static function handleCheckout(): void {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user = $_SESSION['user'] ?? null;
            if (!$user) {
                setFlash('danger', 'Please log in to place an order.');
                header('Location: login.php');
                exit;
            }

            $userId      = (int)$user['user_id'];
            $addressId   = (int)($_POST['address_id'] ?? 0);
            $paymentMode = $_POST['payment_mode'] ?? 'UPI';
            $couponCode  = trim($_POST['coupon_code'] ?? '');
            $notes       = trim($_POST['order_notes'] ?? '');

            $cartJson = $_POST['cart_data'] ?? '';
            $cartItems = json_decode($cartJson, true);

            if (empty($cartItems) && !empty($_SESSION['cart'])) {
                $cartItems = $_SESSION['cart'];
            }

            if (empty($cartItems)) {
                setFlash('danger', 'Your cart is empty. Please add items before checking out.');
                return;
            }

            if ($addressId <= 0) {
                setFlash('danger', 'Please select a valid delivery address.');
                return;
            }

            $result = Order::createOrder($userId, $addressId, $cartItems, $paymentMode, $couponCode, $notes);

            if ($result['success']) {
                $_SESSION['cart'] = [];
                header("Location: order-success.php?order=" . urlencode($result['order_number']));
                exit;
            } else {
                setFlash('danger', 'Order placement failed: ' . $result['message']);
            }
        }
    }

    public static function getCheckoutPageData(): array {
        $userId = (int)($_SESSION['user']['user_id'] ?? 0);
        $addresses = $userId > 0 ? Address::getAllByUserId($userId) : [];
        return [
            'addresses' => $addresses,
            'user'      => $_SESSION['user'] ?? null
        ];
    }
}
