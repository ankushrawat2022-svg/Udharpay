<?php
declare(strict_types=1);

/**
 * Payment & Repayment Controller
 * - Supports full, partial, and installment repayments in clearly labeled DEMO mode
 * - Duplicate transaction protection via Idempotency-Key
 * - Atomic MariaDB transaction updating payments, repayments, credit_bills, and append-only ledger_entries
 */
final class PaymentController
{
    public static function repay(array $user, array $config): never
    {
        RoleMiddleware::requireRole($user, ['customer', 'merchant', 'manager']);
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['bill_id', 'amount', 'payment_type']);

        $billId = (int)$input['bill_id'];
        $amount = Validator::positiveAmount($input['amount'], 'amount');
        $paymentType = in_array($input['payment_type'], ['full', 'partial', 'installment'], true) ? $input['payment_type'] : 'partial';
        $paymentMode = in_array($input['payment_mode'] ?? 'demo_upi', ['demo_upi', 'demo_cash'], true) ? $input['payment_mode'] : 'demo_upi';
        $idempotencyKey = (string)($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($input['idempotency_key'] ?? Security::randomToken(16)));

        $pdo = Database::connection();

        // Duplicate transaction guard
        $dup = $pdo->prepare('SELECT * FROM payments WHERE idempotency_key = :key LIMIT 1');
        $dup->execute(['key' => $idempotencyKey]);
        if ($existing = $dup->fetch()) {
            Response::success(['payment' => $existing, 'duplicate_prevented' => true], 'Duplicate payment safely blocked; returning original receipt.');
        }

        $partner = new DemoPaymentService($config);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'SELECT b.*, c.user_id AS customer_user_id, m.user_id AS merchant_user_id, s.shop_name
                 FROM credit_bills b
                 INNER JOIN customers c ON c.id = b.customer_id
                 INNER JOIN shops s ON s.id = b.shop_id
                 INNER JOIN merchants m ON m.id = s.merchant_id
                 WHERE b.id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $billId]);
            $bill = $stmt->fetch();

            if (!$bill) {
                $pdo->rollBack();
                Response::error('Bill not found.', 404);
            }
            if ($user['role'] === 'customer' && (int)$bill['customer_user_id'] !== (int)$user['id']) {
                $pdo->rollBack();
                Response::error('Unauthorized bill repayment.', 403);
            }
            if (!in_array($bill['status'], ['active', 'partially_paid', 'overdue'], true)) {
                $pdo->rollBack();
                Response::error('Repayments are only allowed on approved active bills.', 422);
            }

            $pending = round((float)$bill['pending_amount'], 2);
            if ($amount > $pending) {
                $pdo->rollBack();
                Response::error(sprintf('Repayment ₹%.2f exceeds pending bill balance ₹%.2f.', $amount, $pending), 422);
            }

            $paymentRef = Security::generateReference('PAY-DEMO');
            $partnerResult = $partner->initiatePayment($paymentRef, $amount, $paymentMode);
            if ($partnerResult['status'] !== 'succeeded') {
                $pdo->rollBack();
                Response::error($partnerResult['message'] ?? 'Payment partner not configured.', 503);
            }

            $pIns = $pdo->prepare(
                'INSERT INTO payments
                  (payment_ref, idempotency_key, shop_id, customer_id, bill_id, amount, payment_mode, payment_type, is_demo, partner_name, partner_transaction_id, status, receipt_number, verified_at)
                 VALUES
                  (:ref, :idem, :shop_id, :cust_id, :bill_id, :amount, :mode, :ptype, 1, :partner, :ptxn, "succeeded", :rcpt, NOW())'
            );
            $pIns->execute([
                'ref' => $paymentRef,
                'idem' => $idempotencyKey,
                'shop_id' => $bill['shop_id'],
                'cust_id' => $bill['customer_id'],
                'bill_id' => $billId,
                'amount' => $amount,
                'mode' => $paymentMode,
                'ptype' => $paymentType,
                'partner' => $partnerResult['partner_name'],
                'ptxn' => $partnerResult['partner_transaction_id'],
                'rcpt' => $partnerResult['receipt_number'],
            ]);
            $paymentId = (int)$pdo->lastInsertId();

            $newPaid = round((float)$bill['paid_amount'] + $amount, 2);
            $newPending = max(0.00, round((float)$bill['total_amount'] - $newPaid, 2));
            $newStatus = $newPending <= 0.00 ? 'paid' : 'partially_paid';

            $pdo->prepare('UPDATE credit_bills SET paid_amount = :paid, pending_amount = :pending, status = :status WHERE id = :id')
                ->execute(['paid' => $newPaid, 'pending' => $newPending, 'status' => $newStatus, 'id' => $billId]);

            $ledger = LedgerService::postRepaymentCredit(
                $pdo,
                (int)$bill['shop_id'],
                (int)$bill['customer_id'],
                $billId,
                $paymentId,
                $amount,
                "DEMO {$paymentType} repayment {$partnerResult['receipt_number']} for bill {$bill['bill_number']}",
                (int)$user['id']
            );

            $pdo->prepare(
                'INSERT INTO repayments (payment_id, bill_id, credit_account_id, allocated_amount, reconciled)
                 VALUES (:pid, :bid, :caid, :amt, 1)'
            )->execute([
                'pid' => $paymentId,
                'bid' => $billId,
                'caid' => $ledger['credit_account_id'],
                'amt' => $amount,
            ]);

            AuditService::notify((int)$bill['merchant_user_id'], 'DEMO Repayment Received', "Received ₹{$amount} ({$paymentType}) against bill {$bill['bill_number']}. Receipt: {$partnerResult['receipt_number']}", 'payment');
            AuditService::notify((int)$bill['customer_user_id'], 'DEMO Payment Receipt Issued', "Paid ₹{$amount} to {$bill['shop_name']}. Receipt: {$partnerResult['receipt_number']}", 'payment');
            AuditService::log((int)$user['id'], (string)$user['role'], 'payment.demo_repayment', 'payments', (string)$paymentId, ['amount' => $amount, 'receipt' => $partnerResult['receipt_number']]);

            $pdo->commit();
            Response::success([
                'payment_id' => $paymentId,
                'payment_ref' => $paymentRef,
                'receipt_number' => $partnerResult['receipt_number'],
                'amount' => $amount,
                'bill_status' => $newStatus,
                'bill_pending' => $newPending,
                'running_balance' => $ledger['running_balance'],
                'is_demo' => true,
            ], 'DEMO repayment completed and reconciled in append-only ledger.', 201);
        } catch (Throwable $e) {
            $pdo->rollBack();
            Response::error('Repayment transaction failed.', 500);
        }
    }
}
