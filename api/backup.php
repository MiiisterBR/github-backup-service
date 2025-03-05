<?php
// Start output buffering to allow us to control the output sent to the client.
ob_start();

// Include required files and libraries
require_once '../inc/config.php';
require_once '../inc/github_api.php';
require_once '../inc/backup_manager.php';
require_once '../inc/logger.php';

// Set the default response header for JSON responses
header('Content-Type: application/json');

// Check if the required POST parameters are set
if (!isset($_POST['encrypted_token']) || !isset($_POST['repo'])) {
    // Clear any previous output and return a JSON error response
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing parameters'
    ]);
    exit;
}

// Retrieve the encrypted token from POST data and decode it
$encrypted_token = $_POST['encrypted_token'];
$decoded = base64_decode($encrypted_token);

// Remove the encryption salt from the decoded token
$salt = ENCRYPTION_SALT;
$token = str_replace($salt, '', $decoded);

// Decode the repository information from JSON
$repo = json_decode($_POST['repo'], true);
if ($repo === null && json_last_error() !== JSON_ERROR_NONE) {
    // Clear output buffer and send error response if JSON decoding fails
    ob_clean();
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid repository data: ' . json_last_error_msg()
    ]);
    exit;
}

// Call the backup function for the repository with the provided token
$result = backupRepository($repo, $token);

// Clear any buffered output before sending the final JSON response
ob_clean();
echo json_encode($result);
exit;
