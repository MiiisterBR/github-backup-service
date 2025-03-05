<?php
// Define encryption salt for the username cookie.
// Note: This salt must match the one used when encrypting the username.
const ENCRYPTION_SALT = '16N!z<X0*85-NuPq';

// Retrieve the username from the cookie, if available.
if (isset($_COOKIE['encrypted_username'])) {
    // Decode the base64-encoded username and remove the salt.
    $decodedUsername = base64_decode($_COOKIE['encrypted_username']);
    $username = str_replace(ENCRYPTION_SALT, '', $decodedUsername);
} else {
    // Use a fallback username if the cookie is not set.
    $username = 'MiiisterBR';
}

// Define the backups directory path.
// The backups directory is located one level up from the current directory,
// inside a folder named 'backups', then separated by the username.
define("BACKUPS_DIR", realpath(__DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'backups') . DIRECTORY_SEPARATOR . $username);
