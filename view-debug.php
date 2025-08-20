<?php
// View last 50 lines of debug.log
require_once(dirname(__FILE__) . '/../../../wp-load.php');

echo "<h2>Debug Log Viewer</h2>\n";

$debug_file = dirname(__FILE__) . '/debug.log';
if (file_exists($debug_file)) {
    $lines = file($debug_file);
    $last_lines = array_slice($lines, -50); // Last 50 lines
    
    echo "<p>Showing last 50 lines from debug.log:</p>\n";
    echo "<pre style='background: #f1f1f1; padding: 10px; overflow-x: auto; font-size: 12px;'>\n";
    foreach ($last_lines as $line) {
        echo htmlspecialchars($line);
    }
    echo "</pre>\n";
} else {
    echo "<p>Debug log file not found at: {$debug_file}</p>\n";
    
    // Check WordPress debug log
    $wp_debug_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($wp_debug_file)) {
        echo "<p>Found WordPress debug log at: {$wp_debug_file}</p>\n";
        $lines = file($wp_debug_file);
        $last_lines = array_slice($lines, -30); // Last 30 lines
        
        echo "<p>Showing last 30 lines from WordPress debug.log:</p>\n";
        echo "<pre style='background: #f1f1f1; padding: 10px; overflow-x: auto; font-size: 12px;'>\n";
        foreach ($last_lines as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>\n";
    } else {
        echo "<p>No WordPress debug log found either.</p>\n";
    }
}

echo "<p><a href='javascript:location.reload()'>Refresh</a></p>\n";
?>
