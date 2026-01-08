<?php
header('Content-Type: application/json');
require_once '../config/db.php'; // Adjust path as needed

try {
    // Validate input
    if (!isset($_GET['query'])) {
        echo json_encode([]);
        exit();
    }

    $searchQuery = '%' . trim($_GET['query']) . '%';
    
    // Get database connection
    $stmt = $pdo->prepare("SELECT id, title FROM courses WHERE title LIKE :query LIMIT 5");
    $stmt->bindParam(':query', $searchQuery, PDO::PARAM_STR);
    $stmt->execute();
    
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format response
    $results = array_map(function($course) {
        return [
            'id' => (int)$course['id'],
            'title' => htmlspecialchars($course['title'], ENT_QUOTES, 'UTF-8')
        ];
    }, $courses);
    
    echo json_encode($results);
    
} catch (PDOException $e) {
    error_log("Search error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>