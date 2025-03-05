<?php
// Include configuration and required libraries
require_once '../inc/config.php';
require_once '../inc/github_api.php';
require_once '../inc/logger.php';

header('Content-Type: application/json');

// Step 1: Validate Input Parameters
// Ensure that encrypted_token, repo_name, and username are provided.
if (!isset($_POST['encrypted_token'], $_POST['repo_name'], $_POST['username'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing parameters'
    ]);
    exit;
}

// Step 2: Decrypt the Token
// Decode the encrypted token and remove the encryption salt.
$encrypted_token = $_POST['encrypted_token'];
$decoded = base64_decode($encrypted_token);
$token = str_replace(ENCRYPTION_SALT, '', $decoded);

// Step 3: Retrieve Parameters
// Get repo_name and username from POST data.
$repo_name = $_POST['repo_name'];
$username = $_POST['username'];

// Determine the owner dynamically:
// If the owner field is provided from POST, use it; otherwise, fallback to a constant value.
$owner = (isset($_POST['owner']) && !empty(trim($_POST['owner']))) ? trim($_POST['owner']) : ORGANIZATION_NAME;

// Step 4: Construct the Repository Full Name
// e.g., "Owner/RepoName"
$repoFullName = "{$owner}/{$repo_name}";

// Step 5: Prepare the API Endpoint URL for DELETE Request
$removeUrl = "https://api.github.com/repos/{$repoFullName}/collaborators/{$username}";

// Step 6: Execute the DELETE Request using the Helper Function
$result = githubApiDelete($removeUrl, $token);

// Step 7: Return the JSON Response
if ($result) {
    echo json_encode([
        'status' => 'success',
        'message' => "Collaborator {$username} removed from repository {$repo_name}."
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => "Failed to remove collaborator {$username} from repository {$repo_name}."
    ]);
}

/**
 * Send a DELETE request to the GitHub API.
 *
 * @param string $url The API endpoint URL.
 * @param string|null $token The GitHub authorization token.
 * @return bool Returns true if the HTTP response code is between 200 and 204, otherwise false.
 */
function githubApiDelete($url, $token = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    // Prepare HTTP headers.
    $headers = [
        'Accept: application/vnd.github.v3+json',
        'User-Agent: BackupScript'
    ];
    if (!empty($token)) {
        $headers[] = 'Authorization: token ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set DELETE as the HTTP method.
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For production, enable SSL verification.

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Return true if the HTTP code indicates success (200-204).
    return ($code >= 200 && $code < 205);
}
