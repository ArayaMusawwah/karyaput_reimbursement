<?php
/**
 * Logging utility for the application
 */

class Logger {
    private static $logFile = __DIR__ . '/../logs/app.log';
    
    public static function init() {
        // Create logs directory if it doesn't exist
        $logDir = dirname(self::$logFile);
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // Create log file if it doesn't exist
        if (!file_exists(self::$logFile)) {
            touch(self::$logFile);
        }
    }
    
    public static function log($level, $message, $context = []) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $user = $_SESSION['user_id'] ?? 'anonymous';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        
        $contextStr = '';
        if (!empty($context)) {
            $contextStr = ' | Context: ' . json_encode($context);
        }
        
        $logEntry = "[$timestamp] [$level] [User: $user] [IP: $ip] [URI: $uri] $message $contextStr" . PHP_EOL;
        
        file_put_contents(self::$logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    public static function info($message, $context = []) {
        self::log('INFO', $message, $context);
    }
    
    public static function error($message, $context = []) {
        self::log('ERROR', $message, $context);
    }
    
    public static function warning($message, $context = []) {
        self::log('WARNING', $message, $context);
    }
    
    public static function debug($message, $context = []) {
        self::log('DEBUG', $message, $context);
    }
}

// Initialize the logger
Logger::init();