<?php
session_start();
include 'includes/db.php';

// Check auth
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$courseId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// Check Course Completion
try {
    // Total Lessons
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM course_lessons WHERE course_id = ?");
    $stmt->execute([$courseId]);
    $totalLessons = $stmt->fetchColumn();
    
    // Completed Lessons
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_progress WHERE user_id = ? AND course_id = ?");
    $stmt->execute([$userId, $courseId]);
    $completedLessons = $stmt->fetchColumn();
    
    if ($totalLessons == 0 || $completedLessons < $totalLessons) {
        die("Course not completed yet. Progress: $completedLessons / $totalLessons");
    }
    
    // Get Details
    $stmt = $pdo->prepare("SELECT title FROM courses WHERE id = ?");
    $stmt->execute([$courseId]);
    $course = $stmt->fetch();
    
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    $courseName = $course['title'];
    $studentName = $user['first_name'] . ' ' . $user['last_name'];
    $date = date("F d, Y");
    $certId = "EDUFY-" . strtoupper(dechex($userId)) . "-" . strtoupper(dechex($courseId)) . "-" . date("ymd");
    
} catch (PDOException $e) {
    die("Error checking progress.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;700&family=Roboto:wght@300;400&display=swap" rel="stylesheet">
    <style>
        body { margin: 0; padding: 0; background: #f0f0f0; font-family: 'Roboto', sans-serif; }
        .certificate-container {
            width: 800px;
            height: 600px;
            background-color: #fff;
            margin: 40px auto;
            padding: 40px;
            text-align: center;
            border: 10px solid #ddd;
            position: relative;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .border-inner {
            border: 2px solid #6B4EF9;
            height: 100%;
            padding: 20px;
            box-sizing: border-box;
            position: relative;
        }
        .header {
            font-family: 'Cinzel', serif;
            font-size: 48px;
            color: #333;
            margin-bottom: 10px;
            margin-top: 40px;
        }
        .sub-header {
            font-size: 20px;
            color: #666;
            margin-bottom: 40px;
        }
        .name-label {
            font-size: 16px;
            color: #999;
        }
        .student-name {
            font-family: 'Cinzel', serif;
            font-size: 36px;
            color: #6B4EF9;
            margin: 10px 0 30px 0;
            border-bottom: 1px solid #ddd;
            display: inline-block;
            padding-bottom: 5px;
            min-width: 300px;
        }
        .course-label {
            font-size: 16px;
            color: #999;
        }
        .course-name {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin: 10px 0 40px 0;
        }
        .footer {
            display: flex;
            justify-content: space-between;
            margin-top: 60px;
            padding: 0 40px;
        }
        .signature, .date {
            text-align: center;
        }
        .line {
            width: 150px;
            height: 1px;
            background: #333;
            margin: 0 auto 10px auto;
        }
        .cert-id {
            position: absolute;
            bottom: 10px;
            right: 20px;
            font-size: 10px;
            color: #ccc;
        }
        .logo {
            width: 80px;
            margin-bottom: 20px;
        }
        @media print {
            body { background: none; -webkit-print-color-adjust: exact; }
            .certificate-container { margin: 0; border: 5px solid #ddd; box-shadow: none; width: 100%; height: 100vh; }
            .no-print { display: none; }
        }
        .btn-download {
            background: #6B4EF9;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            border: none;
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-top: 20px;">
    <button onclick="window.print()" class="btn-download">Download / Print PDF</button>
</div>

<div class="certificate-container">
    <div class="border-inner">
        <!-- Optional Logo -->
        <!-- <img src="assets/img/logo.png" class="logo" alt="Logo"> -->
        
        <div class="header">Certificate of Completion</div>
        <div class="sub-header">This is to certify that</div>
        
        <div class="student-name"><?php echo htmlspecialchars($studentName); ?></div>
        
        <div class="course-label">Has successfully completed the course</div>
        <div class="course-name"><?php echo htmlspecialchars($courseName); ?></div>
        
        <div class="footer">
            <div class="date">
                <div class="line"></div>
                <div><?php echo $date; ?></div>
            </div>
            <div class="signature">
                <div class="line"></div>
                <div>Instructor</div>
            </div>
        </div>
        
        <div class="cert-id">ID: <?php echo htmlspecialchars($certId); ?></div>
    </div>
</div>

</body>
</html>
