<?php
session_start();
require_once 'includes/db.php'; // Your database configuration file

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['review_id'])) {
    $review_id = $_POST['review_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // Verify the review belongs to the user
        $stmt = $pdo->prepare("SELECT * FROM reviews WHERE id = ? AND user_id = ?");
        $stmt->execute([$review_id, $user_id]);
        $review = $stmt->fetch();
        
        if (!$review) {
            $_SESSION['error'] = "Review not found or you don't have permission to delete it.";
            header("Location: courses.php");
            exit();
        }
        
        // Delete the review
        $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
        $stmt->execute([$review_id]);
        
        $_SESSION['success'] = "Your review has been deleted.";
        header("Location: course-details.php?id=" . $review['course_id'] . "#reviews");
        exit();
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    header("Location: courses.php");
    exit();
}
?>