<?php
session_start();
include "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['order_id']) && isset($input['status'])) {
        try {
            $stmt = $pdo->prepare("UPDATE payment_logs SET status = ?, payment_id = ?, error_message = ?, source = ?, updated_at = NOW() WHERE order_id = ?");
            $stmt->execute([
                $input['status'],
                $input['payment_id'] ?? null,
                $input['error'] ?? null,
                $input['source'] ?? 'website', // Add source tracking
                $input['order_id']
            ]);
            
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            error_log("Payment log update error: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
?>