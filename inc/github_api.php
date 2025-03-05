<?php
require_once 'logger.php';

/**
 * Helper function to perform a GET request to the GitHub API.
 *
 * @param string $url The GitHub API endpoint.
 * @param string|null $token Optional GitHub token for authentication.
 * @return mixed Returns the decoded JSON response on success, or null on error.
 */
function githubApiGet($url, $token = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);

    // Prepare HTTP headers
    $headers = [
        'Accept: application/vnd.github.v3+json',
        'User-Agent: BackupScript'
    ];
    if (!empty($token)) {
        $headers[] = 'Authorization: token ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // For production, consider enabling SSL verification.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    // Execute the request
    $result = curl_exec($ch);
    $error  = curl_error($ch);
    $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log any cURL errors and return null if encountered
    if ($error) {
        logMessage("cURL Error: " . $error);
        return null;
    }
    // Check for expected HTTP success codes (200 OK or 201 Created)
    if ($code !== 200 && $code !== 201) {
        logMessage("HTTP Error: Code " . $code . " when calling $url.");
        return null;
    }

    // Decode the JSON response
    return json_decode($result, true);
}
