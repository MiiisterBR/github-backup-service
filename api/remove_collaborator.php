<?php
// Include configuration and required libraries
require_once '../inc/config.php';
require_once '../inc/github_api.php';
require_once '../inc/logger.php';

header('Content-Type: application/json');

// Step 1: Validate Input Parameters
// For removal from a single repository or all repositories, we require at least encrypted_token and username.
// repo_name is optional: if empty, removal will be attempted for all repositories.
if (!isset($_POST['encrypted_token'], $_POST['username'])) {
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
// Get the username and optional repo_name from POST data.
$username = $_POST['username'];
$repo_name = isset($_POST['repo_name']) ? trim($_POST['repo_name']) : '';

// Determine owner dynamically:
// If the 'owner' field is provided (e.g., from orgNameInput), use it; otherwise, default to username.
$owner = (isset($_POST['owner']) && !empty(trim($_POST['owner']))) ? trim($_POST['owner']) : $username;

// Step 4: Removal Logic
// If repo_name is provided (non-empty), remove the collaborator from that specific repository.
// Otherwise, fetch all repositories for the given owner (paginated) and remove the collaborator from each.
if (!empty($repo_name)) {
    // Single repository removal
    $repoFullName = "{$owner}/{$repo_name}";
    $removeUrl = "https://api.github.com/repos/{$repoFullName}/collaborators/{$username}";
    $result = githubApiDelete($removeUrl, $token);

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
} else {
    // Removal from all repositories
    $totalRemoved = 0;
    $successfulRepos = []; // Array to store names of repositories with successful removal
    $page = 1;
    // Determine if we are in organization mode (owner different from username) or user mode.
    $isOrg = ($owner !== $username);
    do {
        if ($isOrg) {
            // Organization mode: fetch organization's repositories
            $reposUrl = "https://api.github.com/orgs/{$owner}/repos?per_page=100&page={$page}";
        } else {
            // User mode: fetch user's repositories (both public and private)
            $reposUrl = "https://api.github.com/user/repos?per_page=100&page={$page}";
        }

        $reposPage = githubApiGet($reposUrl, $token);
        if ($reposPage && count($reposPage) > 0) {
            foreach ($reposPage as $repo) {
                $repoFullName = $repo['full_name'];
                $removeUrl = "https://api.github.com/repos/{$repoFullName}/collaborators/{$username}";
                $result = githubApiDelete($removeUrl, $token);
                if ($result) {
                    $totalRemoved++;
                    $successfulRepos[] = $repoFullName;
                    logMessage("Collaborator {$username} removed from repository {$repoFullName}.");
                } else {
                    logMessage("Failed to remove collaborator {$username} from repository {$repoFullName}.");
                }
            }
            $page++;
        } else {
            break;
        }
    } while (true);

    // Return JSON response based on the removals
    if ($totalRemoved > 0) {
        echo json_encode([
            'status' => 'success',
            'message' => "Collaborator {$username} removed from the following repositories: " . implode(', ', $successfulRepos) . "."
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => "Collaborator {$username} was not removed from any repository."
        ]);
    }
}

/**
 * Send a DELETE request to the GitHub API.
 *
 * @param string $url The API endpoint URL.
 * @param string|null $token The GitHub authorization token.
 * @return bool Returns true if the HTTP response code is in the 200-204 range; otherwise, false.
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For production, consider enabling SSL verification.

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // If collaborator not found (404), treat as success.
    if ($code == 404) {
        logMessage("URL: {$url} | HTTP Code: {$code} | Collaborator not found (considered as removed).");
        return true;
    }

    // Log detailed information if there's an error.
    if (!($code >= 200 && $code < 205)) {
        logMessage("URL: {$url} | HTTP Code: {$code} | Response: " . $response);
    }

    return ($code >= 200 && $code < 205);
}
