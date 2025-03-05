<?php
// Include configuration and required libraries
require_once '../inc/config.php';
require_once '../inc/github_api.php';
require_once '../inc/logger.php';

// Set response header to JSON
header('Content-Type: application/json');

// --- Step 1: Validate and Decrypt the Token ---
// Ensure the encrypted token is provided in the POST data
if (!isset($_POST['encrypted_token'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Token not provided'
    ]);
    exit;
}

// Decrypt token: base64 decode and remove the encryption salt
$encrypted_token = $_POST['encrypted_token'];
$decoded = base64_decode($encrypted_token);
$token = str_replace(ENCRYPTION_SALT, '', $decoded);

// Initialize an empty array to store repositories
$allRepos = [];

// --- Step 2: Determine Mode and Fetch Repositories ---
// If 'org' flag is true, operate in organization mode; otherwise, use user mode.
if (isset($_POST['org']) && $_POST['org'] === "true") {
    // --- Organization Mode ---
    // Check for organization name from cookie or POST data
    if (isset($_COOKIE['organization']) && !empty($_COOKIE['organization'])) {
        $githubOrg = $_COOKIE['organization'];
    } elseif (isset($_POST['organization']) && !empty($_POST['organization'])) {
        $githubOrg = $_POST['organization'];
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Organization not provided'
        ]);
        exit;
    }

    // Fetch organization repositories using pagination
    $page = 1;
    do {
        // Build the GitHub API URL for organization repositories
        $reposUrl = "https://api.github.com/orgs/{$githubOrg}/repos?per_page=100&page={$page}";
        $reposPage = githubApiGet($reposUrl, $token);

        // Merge repositories if the page has data, else break out of the loop
        if ($reposPage && count($reposPage) > 0) {
            $allRepos = array_merge($allRepos, $reposPage);
            $page++;
        } else {
            break;
        }
    } while (true);
} else {
    // --- User Mode ---
    // Retrieve username from POST or decrypt from cookie if not provided in POST data
    if (isset($_POST['username']) && !empty($_POST['username'])) {
        $username = $_POST['username'];
    } elseif (isset($_COOKIE['encrypted_username']) && !empty($_COOKIE['encrypted_username'])) {
        $encrypted_username = $_COOKIE['encrypted_username'];
        $decodedUsername = base64_decode($encrypted_username);
        $username = str_replace(ENCRYPTION_SALT, '', $decodedUsername);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Username not provided'
        ]);
        exit;
    }

    // Fetch user repositories using pagination; using the /user/repos endpoint returns both public and private repos
    $page = 1;
    do {
        $reposUrl = "https://api.github.com/user/repos?per_page=100&page={$page}";
        $reposPage = githubApiGet($reposUrl, $token);

        if ($reposPage && count($reposPage) > 0) {
            $allRepos = array_merge($allRepos, $reposPage);
            $page++;
        } else {
            break;
        }
    } while (true);
}

// --- Step 3: Verify Repository Data ---
// If no repositories were fetched, return an error response
if (empty($allRepos)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Could not fetch repositories.'
    ]);
    exit;
}

// --- Step 4: Optionally Fetch Collaborators for Each Repository ---
// Check if the request has enabled fetching collaborators
$fetchCollaborators = (isset($_POST['fetch_collaborators']) && $_POST['fetch_collaborators'] === 'true');

// Loop through each repository to append collaborators data
foreach ($allRepos as &$repo) {
    if ($fetchCollaborators) {
        // Build the URL to fetch collaborators for the repository
        $repoFullName = $repo['full_name'];
        $collaboratorsUrl = "https://api.github.com/repos/{$repoFullName}/collaborators?per_page=100";
        $collaborators = githubApiGet($collaboratorsUrl, $token);
        $repo['collaborators'] = $collaborators ? $collaborators : [];
    } else {
        // Set collaborators as an empty array if not requested
        $repo['collaborators'] = [];
    }
}
unset($repo); // Unset reference to avoid unintended side effects

// --- Step 5: Return the Final Response ---
// Return the repositories list with collaborators (if fetched) as a JSON response
echo json_encode([
    'status' => 'success',
    'repos' => $allRepos
]);
