<?php
/**
 * Log a message to a log file without displaying it on screen.
 *
 * @param string $message The message to log.
 */
function logMessage($message) {
    // Define the log file path (one directory up from the current directory)
    $logFile = __DIR__ . '/../error_log';

    // Define a separator for readability between log entries
    $separator = str_repeat('-', 60);

    // Append the log message and a newline to the log file
    file_put_contents($logFile, $message . PHP_EOL, FILE_APPEND);
    // Append the separator and a newline to the log file for better readability
    file_put_contents($logFile, $separator . PHP_EOL, FILE_APPEND);
}
