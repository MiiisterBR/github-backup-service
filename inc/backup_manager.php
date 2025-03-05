<?php
require_once 'config.php';
require_once 'github_api.php';
require_once 'logger.php';

/**
 * Backup a given repository (all branches) if there are updates.
 *
 * If the repository is empty (no branches found), mark its status as "empty"
 * and do not perform any backup.
 *
 * Files are stored in: BACKUPS_DIR/repo_name/YYYYMMDD/branch.zip
 *
 * @param array $repo  Repository data from GitHub API
 * @param string $token  GitHub token (decrypted)
 * @return array  Result status and message
 */
function backupRepository($repo, $token) {
    $repoName     = $repo['name'];
    $repoFullName = $repo['full_name'];
    $todayDate    = date('Ymd');
    $repoBackupDir = BACKUPS_DIR . "/{$repoName}/{$todayDate}";

    // Create backup directory if it doesn't exist
    if (!is_dir($repoBackupDir)) {
        mkdir($repoBackupDir, 0755, true);
    }

    // Retrieve repository branches
    $branchesUrl = "https://api.github.com/repos/{$repoFullName}/branches?per_page=100";
    $branches = githubApiGet($branchesUrl, $token);

    // If no branches found, mark repository as empty and exit backup
    if (empty($branches)) {
        logMessage("Repository {$repoName} is empty (no branches found).", false);
        return ['status' => 'empty', 'message' => "Repository {$repoName} is empty"];
    }

    // Load metadata (last backup data) if available
    $metadataFile = BACKUPS_DIR . "/{$repoName}/last_backup.json";
    $lastBackupData = file_exists($metadataFile) ? json_decode(file_get_contents($metadataFile), true) : [];

    $repoUpdated = false;
    foreach ($branches as $branch) {
        // Check for cancellation flag (if cancel button pressed)
        $cancelFlagFile = BACKUPS_DIR . "/cancel_{$repoName}.flag";
        if (file_exists($cancelFlagFile)) {
            unlink($cancelFlagFile);
            logMessage("Backup cancelled for repository {$repoName}.");
            return ['status' => 'cancelled', 'message' => "Backup cancelled for repository {$repoName}"];
        }

        $branchName      = $branch['name'];
        $latestCommitSha = $branch['commit']['sha'];

        // Skip downloading if branch has not changed since last backup
        if (isset($lastBackupData[$branchName]) && $lastBackupData[$branchName] === $latestCommitSha) {
            logMessage("Branch {$branchName} of repository {$repoName} has no updates since last backup.");
            continue;
        }
        $repoUpdated = true;

        // Sanitize branch name for file system safety
        $safeBranchName = str_replace(['/', '\\'], '_', $branchName);
        $zipUrl         = "https://api.github.com/repos/{$repoFullName}/zipball/{$branchName}";
        $zipPath        = $repoBackupDir . "/{$safeBranchName}.zip";

        logMessage("Downloading branch: {$branchName} to file: {$zipPath}");

        // Initialize cURL for downloading the branch zip
        $chZip = curl_init($zipUrl);
        $zipHeaders = [
            'Accept: application/vnd.github.v3.raw',
            'User-Agent: BackupScript'
        ];
        if (!empty($token)) {
            $zipHeaders[] = 'Authorization: token ' . $token;
        }
        curl_setopt($chZip, CURLOPT_HTTPHEADER, $zipHeaders);
        curl_setopt($chZip, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($chZip, CURLOPT_MAXREDIRS, 5);

        // Open file handle for writing the zip file
        $fp = fopen($zipPath, 'w');
        if (!$fp) {
            logMessage("Could not open file for writing: {$zipPath}");
            continue;
        }
        curl_setopt($chZip, CURLOPT_FILE, $fp);
        curl_setopt($chZip, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($chZip);

        // Gather cURL info and errors
        $errorZip = curl_error($chZip);
        $codeZip  = curl_getinfo($chZip, CURLINFO_HTTP_CODE);
        curl_close($chZip);
        fclose($fp);

        logMessage("HTTP code for {$zipUrl}: {$codeZip}");
        logMessage("cURL error for {$zipUrl}: {$errorZip}");

        if ($errorZip) {
            logMessage("Error while downloading zip: {$errorZip}");
        } elseif ($codeZip !== 200 && $codeZip !== 302) {
            logMessage("HTTP error code: {$codeZip}");
        } else {
            logMessage("Zip file downloaded successfully for branch {$branchName}.");
            // Update metadata for this branch with the latest commit SHA
            $lastBackupData[$branchName] = $latestCommitSha;
        }
    }

    // Save updated metadata if any branch was updated
    if ($repoUpdated) {
        file_put_contents($metadataFile, json_encode($lastBackupData));
    }

    return ['status' => 'success', 'updated' => $repoUpdated, 'repo' => $repoName];
}

/**
 * Backup all repositories one by one.
 *
 * @param array $repos  List of repositories from GitHub API
 * @param string $token  GitHub token (decrypted)
 * @return array  List of backup results per repository
 */
function backupAllRepositories($repos, $token) {
    $results = [];
    foreach ($repos as $repo) {
        $result = backupRepository($repo, $token);
        $results[] = $result;
    }
    return $results;
}
