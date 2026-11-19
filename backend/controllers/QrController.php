<?php
declare(strict_types=1);

/**
 * QR Code Workflow Controller
 * CRITICAL RULE: Scanning a Shop QR NEVER creates debt automatically.
 * It only returns verified public shop details (zero customer PII in QR).
 */
final class QrController
{
    public static function resolveShopQr(string $qrToken): never
    {
        $cleanToken = Validator::cleanString($qrToken, 64);
        $shop = ShopModel::findByQrToken($cleanToken);
        if (!$shop) {
            Response::error('Invalid or inactive Shop QR code.', 404);
        }

        Response::success([
            'shop' => $shop,
            'debt_created_on_scan' => false,
            'notice' => 'Scanning this shop QR only displays verified merchant details. Any Udhar credit bill requires your explicit approval.',
        ]);
    }

    public static function resolveBillQr(string $billQrToken, array $user): never
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'SELECT b.*, s.shop_name, s.shop_code, s.is_verified AS shop_verified, c.user_id AS customer_user_id
             FROM credit_bills b
             INNER JOIN shops s ON s.id = b.shop_id
             INNER JOIN customers c ON c.id = b.customer_id
             WHERE b.bill_qr_token = :token
             LIMIT 1'
        );
        $stmt->execute(['token' => Validator::cleanString($billQrToken, 64)]);
        $bill = $stmt->fetch();

        if (!$bill) {
            Response::error('Bill QR link not found.', 404);
        }
        if ($user['role'] === 'customer' && (int)$bill['customer_user_id'] !== (int)$user['id']) {
            Response::error('You are not authorized to view this customer bill.', 403);
        }

        $bill['items'] = CreditBillModel::itemsForBill((int)$bill['id']);
        Response::success(['bill' => $bill]);
    }
}
