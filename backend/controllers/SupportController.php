<?php
declare(strict_types=1);

final class SupportController
{
    public static function listTickets(array $user): never
    {
        RoleMiddleware::requireRole($user, ['support', 'super_admin']);
        $status = isset($_GET['status']) ? Validator::cleanString((string)$_GET['status'], 30) : null;
        Response::success(['tickets' => SupportModel::listTickets($status)]);
    }

    public static function createComplaint(array $user): never
    {
        RoleMiddleware::requireRole($user, ['customer', 'merchant']);
        $input = Validator::readJsonBody();
        Validator::requireFields($input, ['category', 'subject', 'description']);

        $category = in_array($input['category'], ['incorrect_bill', 'duplicate_payment', 'mandate_issue', 'merchant_conduct', 'customer_default', 'other'], true)
            ? $input['category'] : 'other';
        $subject = Validator::cleanString((string)$input['subject'], 150);
        $desc = Validator::cleanString((string)$input['description'], 1500);

        $pdo = Database::connection();
        $pdo->beginTransaction();
        try {
            $ref = Security::generateReference('CMP');
            $stmt = $pdo->prepare(
                'INSERT INTO complaints (complaint_ref, submitted_by_user_id, shop_id, bill_id, category, subject, description, status)
                 VALUES (:ref, :uid, :sid, :bid, :cat, :sub, :desc, "open")'
            );
            $stmt->execute([
                'ref' => $ref,
                'uid' => $user['id'],
                'sid' => isset($input['shop_id']) ? (int)$input['shop_id'] : null,
                'bid' => isset($input['bill_id']) ? (int)$input['bill_id'] : null,
                'cat' => $category,
                'sub' => $subject,
                'desc' => $desc,
            ]);
            $complaintId = (int)$pdo->lastInsertId();

            $tktCat = match ($category) {
                'duplicate_payment' => 'duplicate_payment',
                'incorrect_bill' => 'incorrect_bill',
                'mandate_issue' => 'mandate',
                default => $user['role'] === 'merchant' ? 'merchant' : 'customer',
            };
            $ticketNo = Security::generateReference('TKT');
            $pdo->prepare(
                'INSERT INTO support_tickets (ticket_number, complaint_id, requester_user_id, priority, category, subject, status)
                 VALUES (:num, :cid, :uid, "medium", :cat, :sub, "open")'
            )->execute([
                'num' => $ticketNo,
                'cid' => $complaintId,
                'uid' => $user['id'],
                'cat' => $tktCat,
                'sub' => $subject,
            ]);

            $pdo->commit();
            Response::success(['complaint_ref' => $ref, 'ticket_number' => $ticketNo], 'Complaint submitted and support ticket opened.', 201);
        } catch (Throwable) {
            $pdo->rollBack();
            Response::error('Failed to submit complaint.', 500);
        }
    }

    public static function updateTicket(int $ticketId, array $user): never
    {
        RoleMiddleware::requireRole($user, ['support', 'super_admin']);
        $input = Validator::readJsonBody();

        $status = in_array($input['status'] ?? '', ['open', 'assigned', 'awaiting_user', 'escalated', 'resolved', 'closed'], true)
            ? $input['status'] : 'assigned';
        $reply = Validator::cleanString((string)($input['reply'] ?? ''), 1500);

        $pdo = Database::connection();
        $stmt = $pdo->prepare(
            'UPDATE support_tickets
             SET status = :st, assigned_agent_id = :agent, last_reply = CASE WHEN :reply != "" THEN :reply2 ELSE last_reply END
             WHERE id = :id'
        );
        $stmt->execute([
            'st' => $status,
            'agent' => $user['id'],
            'reply' => $reply,
            'reply2' => $reply,
            'id' => $ticketId,
        ]);

        AuditService::log((int)$user['id'], (string)$user['role'], 'support.ticket_updated', 'support_tickets', (string)$ticketId, ['status' => $status]);
        Response::success(['ticket_id' => $ticketId, 'status' => $status], 'Support ticket updated.');
    }
}
