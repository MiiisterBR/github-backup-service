<?php
// Include configuration and required libraries
require_once '../inc/config.php';
require_once '../inc/github_api.php';
require_once '../inc/logger.php';

header('Content-Type: application/json');

// Step 1: Validate Input Parameters
// We require encrypted_token, repo_name, username, and access.
if (!isset($_POST['encrypted_token'], $_POST['repo_name'], $_POST['username'], $_POST['access'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing parameters'
    ]);
    exit;
}

// Step 2: Decrypt the Token
// Decode the encrypted token using base64 and remove the encryption salt.
$encrypted_token = $_POST['encrypted_token'];
$decoded = base64_decode($encrypted_token);
$token = str_replace(ENCRYPTION_SALT, '', $decoded);

// Step 3: Retrieve Parameters from POST
$repo_name = $_POST['repo_name'];
$username = $_POST['username'];
$access = $_POST['access'];

// Step 4: Determine the Repository Owner Dynamically
// If an 'owner' is provided via POST (e.g. from orgNameInput), use it; otherwise, default to username.
$owner = (isset($_POST['owner']) && !empty(trim($_POST['owner']))) ? trim($_POST['owner']) : $username;

// Step 5: Construct the Repository Full Name (e.g., "Owner/RepoName")
$repoFullName = "{$owner}/{$repo_name}";

// Step 6: Prepare the API Endpoint URL for Updating Collaborator Permission
$updateUrl = "https://api.github.com/repos/{$repoFullName}/collaborators/{$username}";

// Step 7: Prepare the JSON Payload with the New Permission
$data = json_encode(['permission' => $access]);

// Step 8: Send the PUT Request using the Helper Function
$result = githubApiPut($updateUrl, $token, $data);

// Step 9: Return the JSON Response based on the API result
if ($result) {
    echo json_encode([
        'status' => 'success',
        'message' => "Collaborator {$username}'s access updated to {$access} in repository {$repo_name}."
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => "Failed to update access for {$username} in repository {$repo_name}."
    ]);
}

/**
 * Sends a PUT request to the GitHub API.
 *
 * @param string $url The API endpoint URL.
 * @param string|null $token The GitHub authorization token.
 * @param string|null $data The JSON encoded payload.
 * @return bool Returns true if HTTP code is 201 (Created) or 204 (No Content); otherwise, false.
 */
function githubApiPut($url, $token = null, $data = null)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    // Set required headers including JSON content type.
    $headers = [
        'Accept: application/vnd.github.v3+json',
        'User-Agent: BackupScript',
        'Content-Type: application/json'
    ];
    if (!empty($token)) {
        $headers[] = 'Authorization: token ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    // Set the request method to PUT and attach the payload if provided.
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }

    // Return the response instead of printing.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // For production, consider enabling SSL verification.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Execute the PUT request.
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Return true if the HTTP response code indicates success.
    return ($code == 201 || $code == 204);
}
