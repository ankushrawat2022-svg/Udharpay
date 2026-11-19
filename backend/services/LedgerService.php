<?php
declare(strict_types=1);

/**
 * Append-Only Credit Ledger Engine
 * All financial mutations execute inside ACID MariaDB transactions with row locking (FOR UPDATE).
 */
final class LedgerService
{
    public static function ensureCreditAccount(PDO $pdo, int $shopId, int $customerId): array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM credit_accounts WHERE shop_id = :shop_id AND customer_id = :customer_id FOR UPDATE'
        );
        $stmt->execute(['shop_id' => $shopId, 'customer_id' => $customerId]);
        $account = $stmt->fetch();

        if ($account) {
            return $account;
        }

        $insert = $pdo->prepare(
            'INSERT INTO credit_accounts (shop_id, customer_id, total_credited, total_repaid, outstanding_balance, status, last_activity_at)
             VALUES (:shop_id, :customer_id, 0.00, 0.00, 0.00, "active", NOW())'
        );
        $insert->execute(['shop_id' => $shopId, 'customer_id' => $customerId]);

        $stmt->execute(['shop_id' => $shopId, 'customer_id' => $customerId]);
        return (array)$stmt->fetch();
    }

    public static function postCreditDebit(PDO $pdo, int $shopId, int $customerId, int $billId, float $amount, string $description, int $actorUserId): array
    {
        $account = self::ensureCreditAccount($pdo, $shopId, $customerId);

        $newTotalCredited = round((float)$account['total_credited'] + $amount, 2);
        $newBalance = round((float)$account['outstanding_balance'] + $amount, 2);
        $ref = Security::generateReference('LED');

        $upd = $pdo->prepare(
            'UPDATE credit_accounts
             SET total_credited = :total_credited,
                 outstanding_balance = :outstanding_balance,
                 status = "active",
                 last_activity_at = NOW()
             WHERE id = :id'
        );
        $upd->execute([
            'total_credited' => $newTotalCredited,
            'outstanding_balance' => $newBalance,
            'id' => $account['id'],
        ]);

        $ins = $pdo->prepare(
            'INSERT INTO ledger_entries
              (reference_number, credit_account_id, shop_id, customer_id, bill_id, entry_type, amount, running_balance, description, created_by_user_id)
             VALUES
              (:ref, :account_id, :shop_id, :customer_id, :bill_id, "credit_debit", :amount, :running_balance, :description, :actor_id)'
        );
        $ins->execute([
            'ref' => $ref,
            'account_id' => $account['id'],
            'shop_id' => $shopId,
            'customer_id' => $customerId,
            'bill_id' => $billId,
            'amount' => $amount,
            'running_balance' => $newBalance,
            'description' => $description,
            'actor_id' => $actorUserId,
        ]);

        return ['reference_number' => $ref, 'running_balance' => $newBalance];
    }

    public static function postRepaymentCredit(PDO $pdo, int $shopId, int $customerId, int $billId, int $paymentId, float $amount, string $description, int $actorUserId): array
    {
        $account = self::ensureCreditAccount($pdo, $shopId, $customerId);

        $newTotalRepaid = round((float)$account['total_repaid'] + $amount, 2);
        $newBalance = max(0.00, round((float)$account['outstanding_balance'] - $amount, 2));
        $status = $newBalance <= 0.00 ? 'settled' : 'active';
        $ref = Security::generateReference('LED');

        $upd = $pdo->prepare(
            'UPDATE credit_accounts
             SET total_repaid = :total_repaid,
                 outstanding_balance = :outstanding_balance,
                 status = :status,
                 last_activity_at = NOW()
             WHERE id = :id'
        );
        $upd->execute([
            'total_repaid' => $newTotalRepaid,
            'outstanding_balance' => $newBalance,
            'status' => $status,
            'id' => $account['id'],
        ]);

        $ins = $pdo->prepare(
            'INSERT INTO ledger_entries
              (reference_number, credit_account_id, shop_id, customer_id, bill_id, payment_id, entry_type, amount, running_balance, description, created_by_user_id)
             VALUES
              (:ref, :account_id, :shop_id, :customer_id, :bill_id, :payment_id, "repayment_credit", :amount, :running_balance, :description, :actor_id)'
        );
        $ins->execute([
            'ref' => $ref,
            'account_id' => $account['id'],
            'shop_id' => $shopId,
            'customer_id' => $customerId,
            'bill_id' => $billId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'running_balance' => $newBalance,
            'description' => $description,
            'actor_id' => $actorUserId,
        ]);

        return ['reference_number' => $ref, 'running_balance' => $newBalance, 'credit_account_id' => (int)$account['id']];
    }
}
