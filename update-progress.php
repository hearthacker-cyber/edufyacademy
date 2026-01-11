<?php
session_start();
include "include/db.php";


if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = $_SESSION['user_id'];
    $courseId = $_POST['course_id'];
    $lessonId = $_POST['lesson_id'];
    $action = $_POST['action'] ?? 'complete';

    try {
        if ($action === 'complete') {
            // Check if already marked
            $stmt = $pdo->prepare("SELECT id FROM user_progress WHERE user_id = ? AND lesson_id = ?");
            $stmt->execute([$userId, $lessonId]);
            
            if (!$stmt->fetch()) {
                $stmt = $pdo->prepare("INSERT INTO user_progress (user_id, course_id, lesson_id) VALUES (?, ?, ?)");
                $stmt->execute([$userId, $courseId, $lessonId]);
            }
        } elseif ($action === 'uncomplete') {
            $stmt = $pdo->prepare("DELETE FROM user_progress WHERE user_id = ? AND lesson_id = ?");
            $stmt->execute([$userId, $lessonId]);
        }

        // Calculate new progress
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_lessons WHERE course_id = ?");
        $stmt->execute([$courseId]);
        $totalLessons = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$userId, $courseId]);
        $completedLessons = $stmt->fetchColumn();

        $percentage = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100) : 0;
        
        // Ensure it doesn't exceed 100
        if ($percentage > 100) $percentage = 100;

        echo json_encode(['success' => true, 'progress' => $percentage]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>