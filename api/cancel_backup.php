<?php
// Include configuration file which also defines BACKUPS_DIR
require_once '../inc/config.php';

// Set the content type to JSON for all responses
header('Content-Type: application/json');

// Ensure the 'repo_name' POST parameter is provided
if (!isset($_POST['repo_name'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Repository name not provided'
    ]);
    exit;
}

// Sanitize the repository name by allowing only letters, numbers, underscores, and dashes
$repoName = preg_replace('/[^a-zA-Z0-9_-]/', '', $_POST['repo_name']);

// Build the cancellation flag file path using the sanitized repository name
$cancelFlagFile = BACKUPS_DIR . "/cancel_{$repoName}.flag";

// Write a simple cancel flag message into the file. This file can be checked elsewhere to cancel the backup process.
file_put_contents($cancelFlagFile, 'cancel');

// Return a JSON response confirming the cancellation request
echo json_encode([
    'status' => 'success',
    'message' => "Backup cancellation requested for repo {$repoName}"
]);
