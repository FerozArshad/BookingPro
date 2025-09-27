<?php
/**
 * Centralized Logging System for BookingPro
 * Replaces scattered debug logging with structured, filterable logs
 */

if (!defined('ABSPATH')) exit;

class BSP_Logger {
    
    private static $instance = null;
    private $log_file;
    private $log_level;
    private $max_context_length = 500; // Limit context size
    
    const ERROR = 1;
    const WARN = 2; 
    const INFO = 3;
    const DEBUG = 4;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->log_file = dirname(__DIR__) . '/bsp-debug.log';
        $this->log_level = defined('WP_DEBUG') && WP_DEBUG ? self::DEBUG : self::WARN;
    }
    
    /**
     * Set logging level (ERROR, WARN, INFO, DEBUG)
     */
    public function set_level($level) {
        $this->log_level = $level;
    }
    
    /**
     * Log error messages (always logged)
     */
    public function error($message, $context = []) {
        $this->log(self::ERROR, 'ERROR', $message, $context);
    }
    
    /**
     * Log warning messages  
     */
    public function warn($message, $context = []) {
        if ($this->log_level >= self::WARN) {
            $this->log(self::WARN, 'WARN', $message, $context);
        }
    }
    
    /**
     * Log informational messages
     */
    public function info($message, $context = []) {
        if ($this->log_level >= self::INFO) {
            $this->log(self::INFO, 'INFO', $message, $context);
        }
    }
    
    /**
     * Log debug messages (only in debug mode)
     */
    public function debug($message, $context = []) {
        if ($this->log_level >= self::DEBUG) {
            $this->log(self::DEBUG, 'DEBUG', $message, $context);
        }
    }
    
    /**
     * Central logging method with memory and size optimization
     */
    private function log($level, $level_name, $message, $context = []) {
        // Memory usage tracking
        $memory_usage = round(memory_get_usage() / 1024 / 1024, 2) . 'MB';
        
        // Limit context size to prevent memory bloat
        $context_str = '';
        if (!empty($context)) {
            $context_json = wp_json_encode($context);
            if (strlen($context_json) > $this->max_context_length) {
                $context_str = substr($context_json, 0, $this->max_context_length) . '... [TRUNCATED]';
            } else {
                $context_str = $context_json;
            }
        }
        
        // Format: [timestamp] [LEVEL] [memory] [session] message | context
        $session_id = $this->get_current_session_id();
        $timestamp = current_time('Y-m-d H:i:s');
        
        $log_entry = "[{$timestamp}] [{$level_name}] [{$memory_usage}] [{$session_id}] {$message}";
        if ($context_str) {
            $log_entry .= " | {$context_str}";
        }
        $log_entry .= "\n";
        
        // Write to log file with error handling
        if (is_writable(dirname($this->log_file))) {
            file_put_contents($this->log_file, $log_entry, FILE_APPEND | LOCK_EX);
        }
        
        // Also log to PHP error log for critical errors
        if ($level === self::ERROR) {
            error_log("BSP ERROR: {$message}");
        }
    }
    
    /**
     * Get current session ID for log correlation
     */
    private function get_current_session_id() {
        // Try to get from POST data first
        if (!empty($_POST['session_id'])) {
            return substr($_POST['session_id'], -8); // Last 8 chars for brevity
        }
        
        // Try to get from existing session
        if (session_id()) {
            return substr(session_id(), -8);
        }
        
        return 'no-session';
    }
    
    /**
     * Log rotation to prevent huge log files
     */
    public function rotate_log() {
        if (file_exists($this->log_file) && filesize($this->log_file) > 5 * 1024 * 1024) { // 5MB
            $backup_file = $this->log_file . '.bak';
            if (file_exists($backup_file)) {
                unlink($backup_file);
            }
            rename($this->log_file, $backup_file);
        }
    }
    
    /**
     * Performance metrics logging
     */
    public function performance($operation, $start_time, $context = []) {
        $duration = microtime(true) - $start_time;
        $duration_ms = round($duration * 1000, 2);
        
        $this->info("PERFORMANCE: {$operation} completed in {$duration_ms}ms", array_merge($context, [
            'duration_ms' => $duration_ms,
            'memory_peak' => round(memory_get_peak_usage() / 1024 / 1024, 2) . 'MB'
        ]));
    }
}

/**
 * Global logging functions for backward compatibility
 */
function bsp_log_error($message, $context = []) {
    BSP_Logger::get_instance()->error($message, $context);
}

function bsp_log_warn($message, $context = []) {
    BSP_Logger::get_instance()->warn($message, $context);
}

function bsp_log_info($message, $context = []) {
    BSP_Logger::get_instance()->info($message, $context);
}

function bsp_log_debug($message, $context = []) {
    BSP_Logger::get_instance()->debug($message, $context);
}

// Initialize logger and set up log rotation
add_action('init', function() {
    BSP_Logger::get_instance()->rotate_log();
});