<?php
declare(strict_types=1);

/**
 * UdharPay Core PHP 8.3+ REST API Router
 */
final class ApiRouter
{
    public static function dispatch(string $method, string $path, array $config): never
    {
        // Health check (Public)
        if ($method === 'GET' && $path === '/api/health') {
            Response::success([
                'service' => 'UdharPay Core PHP 8.3+ REST API',
                'payment_mode' => $config['payment_mode'],
                'database' => Database::healthCheck(),
            ]);
        }

        // Public Authentication Routes
        if ($method === 'POST' && $path === '/api/auth/register') AuthController::register($config);
        if ($method === 'POST' && $path === '/api/auth/login') AuthController::login($config);
        if ($method === 'POST' && $path === '/api/auth/otp/request') AuthController::requestOtp();
        if ($method === 'POST' && $path === '/api/auth/otp/verify') AuthController::verifyOtp($config);

        // Public Shop QR Resolution (Zero Customer PII, Zero Auto-Debt)
        if ($method === 'GET' && preg_match('#^/api/qr/shop/([A-Za-z0-9\-_]+)$#', $path, $m)) {
            QrController::resolveShopQr($m[1]);
        }

        // Payment Partner Webhook Endpoint (Verified Server-Side via HMAC)
        if ($method === 'POST' && preg_match('#^/api/webhooks/([A-Za-z0-9_\-]+)$#', $path, $m)) {
            WebhookController::receive($m[1], $config);
        }

        // Authenticated Routes
        $user = AuthMiddleware::authenticate();
        CsrfMiddleware::verify($user);

        if ($method === 'GET'  && $path === '/api/auth/me') AuthController::me($user);
        if ($method === 'POST' && $path === '/api/auth/logout') AuthController::logout($user);

        // Authenticated Bill QR Lookup
        if ($method === 'GET' && preg_match('#^/api/qr/bill/([A-Za-z0-9\-_]+)$#', $path, $m)) {
            QrController::resolveBillQr($m[1], $user);
        }

        // Merchant Routes
        if ($method === 'GET'  && $path === '/api/merchant/dashboard') MerchantController::dashboard($user);
        if ($method === 'POST' && $path === '/api/merchant/shops') MerchantController::createShop($user);
        if ($method === 'POST' && $path === '/api/merchant/credit-limits') MerchantController::updateCustomerLimit($user);

        // Bills & Customer Explicit Approval
        if ($method === 'POST' && $path === '/api/bills') BillController::create($user);
        if ($method === 'POST' && preg_match('#^/api/bills/(\d+)/decide$#', $path, $m)) {
            BillController::decide((int)$m[1], $user);
        }

        // Customer Dashboard & Mandates
        if ($method === 'GET'  && $path === '/api/customer/dashboard') CustomerController::dashboard($user);
        if ($method === 'POST' && preg_match('#^/api/mandates/(\d+)/cancel$#', $path, $m)) {
            MandateController::cancel((int)$m[1], $user, $config);
        }

        // Repayments (DEMO Mode)
        if ($method === 'POST' && $path === '/api/payments/repay') PaymentController::repay($user, $config);

        // Super Admin Routes
        if ($method === 'GET'  && $path === '/api/admin/overview') AdminController::overview($user);
        if ($method === 'POST' && preg_match('#^/api/admin/merchants/(\d+)/verify$#', $path, $m)) {
            AdminController::verifyMerchant((int)$m[1], $user);
        }

        // Support & Complaints
        if ($method === 'GET'  && $path === '/api/support/tickets') SupportController::listTickets($user);
        if ($method === 'POST' && $path === '/api/complaints') SupportController::createComplaint($user);
        if ($method === 'PATCH' && preg_match('#^/api/support/tickets/(\d+)$#', $path, $m)) {
            SupportController::updateTicket((int)$m[1], $user);
        }

        // Finance & Manager Panels
        if ($method === 'GET' && $path === '/api/finance/overview') FinanceController::overview($user);
        if ($method === 'GET' && $path === '/api/manager/shops') ManagerController::assignedShops($user);

        Response::error('API endpoint not found.', 404);
    }
}
