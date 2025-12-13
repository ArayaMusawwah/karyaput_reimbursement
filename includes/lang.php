<?php
/**
 * Language utility for the application
 */

class Language {
    private static $currentLanguage = 'en'; // Default to English
    private static $translations = [];

    public static function setLanguage($lang) {
        self::$currentLanguage = $lang;
        
        if ($lang === 'id') {
            include_once 'lang_id.php';
            self::$translations = $translations;
        }
    }

    public static function getLanguage() {
        return self::$currentLanguage;
    }

    public static function translate($text) {
        if (self::$currentLanguage === 'id' && isset(self::$translations[$text])) {
            return self::$translations[$text];
        }
        return $text;
    }

    public static function t($text) {
        return self::translate($text);
    }
}

// Initialize with default language based on session or user preference
if (isset($_SESSION['language'])) {
    Language::setLanguage($_SESSION['language']);
} else {
    Language::setLanguage('id'); // Default to Indonesian
}