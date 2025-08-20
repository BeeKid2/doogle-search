<?php
include("../config.php");
include("../classes/AnalyticsTracker.php");

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['search_term']) || !isset($input['result_id']) || !isset($input['result_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
    exit;
}

$searchTerm = $input['search_term'];
$resultId = (int)$input['result_id'];
$resultType = $input['result_type']; // 'site' or 'image'

// Validate result type
if (!in_array($resultType, ['site', 'image'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid result type']);
    exit;
}

try {
    // Initialize analytics tracker
    $analytics = new AnalyticsTracker($con);
    
    // Track the click
    $success = $analytics->trackClick($searchTerm, $resultId, $resultType);
    
    // Also update the clicks count in the respective table
    if ($resultType === 'site') {
        $stmt = $con->prepare("UPDATE sites SET clicks = clicks + 1 WHERE id = ?");
        $stmt->execute([$resultId]);
    } else {
        $stmt = $con->prepare("UPDATE images SET clicks = clicks + 1 WHERE id = ?");
        $stmt->execute([$resultId]);
    }
    
    echo json_encode(['success' => $success]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>