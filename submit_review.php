<?php
session_start();
require_once 'includes/db.php'; // Your database configuration file

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['course_id'], $_POST['rating'])) {
    $course_id = $_POST['course_id'];
    $user_id = $_SESSION['user_id'];
    $rating = (int)$_POST['rating'];
    $review_text = isset($_POST['review_text']) ? trim($_POST['review_text']) : null;
    
    try {
        // Check if user is enrolled in the course
        $stmt = $pdo->prepare("SELECT * FROM enrollments WHERE user_id = ? AND course_id = ? AND payment_status = 'completed'");
        $stmt->execute([$user_id, $course_id]);
        $enrollment = $stmt->fetch();
        
        if (!$enrollment) {
            $_SESSION['error'] = "You must be enrolled in the course to submit a review.";
            header("Location: course-details.php?id=$course_id");
            exit();
        }
        
        // Check if user already reviewed this course
        $stmt = $pdo->prepare("SELECT * FROM reviews WHERE user_id = ? AND course_id = ?");
        $stmt->execute([$user_id, $course_id]);
        $existing_review = $stmt->fetch();
        
        if ($existing_review) {
            // Update existing review
            $stmt = $pdo->prepare("UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$rating, $review_text, $existing_review['id']]);
            $_SESSION['success'] = "Your review has been updated.";
        } else {
            // Insert new review
            $stmt = $pdo->prepare("INSERT INTO reviews (user_id, course_id, rating, review_text) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $course_id, $rating, $review_text]);
            $_SESSION['success'] = "Thank you for your review!";
        }
        
        header("Location: course-details.php?id=$course_id#reviews");
        exit();
        
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    header("Location: course.php");
    exit();
}
?>