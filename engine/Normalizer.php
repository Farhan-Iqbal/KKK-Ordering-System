<?php
// engine/Normalizer.php

class KKKNormalizer {

    /**
     * Normalizes names without blindly applying Title Case to preserve special Malay name structures (e.g. bin, binti, a/l, a/p).
     */
    public static function cleanName($name) {
        if (empty($name)) return '';
        $name = trim(preg_replace('/\s+/', ' ', $name));
        
        // Capitalize words safely
        $words = explode(' ', strtolower($name));
        $specialLowercase = ['bin', 'binti', 'a/l', 'a/p', 'al', 'ap', 'bt', 'b.'];
        
        $cleanedWords = array_map(function($word) use ($specialLowercase) {
            if (in_array($word, $specialLowercase)) {
                return $word;
            }
            return ucfirst($word);
        }, $words);

        return implode(' ', $cleanedWords);
    }

    /**
     * Cleans phone numbers to a consistent Malaysian standard format.
     */
    public static function cleanPhone($phone) {
        if (empty($phone)) return '';
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        if (strpos($phone, '0') === 0) {
            $phone = '+60' . substr($phone, 1);
        }
        return $phone;
    }

    /**
     * Formats design codes to uppercase.
     */
    public static function cleanDesignCode($code) {
        if (empty($code)) return '';
        return strtoupper(trim($code));
    }

    /**
     * Standardizes Gregorian date display format.
     */
    public static function formatDate($dateStr) {
        if (empty($dateStr)) return '';
        $timestamp = strtotime($dateStr);
        if (!$timestamp) return trim($dateStr);

        $months = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Mac', 4 => 'April',
            5 => 'Mei', 6 => 'Jun', 7 => 'Julai', 8 => 'Ogos',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Disember'
        ];
        
        $day = date('j', $timestamp);
        $monthNum = date('n', $timestamp);
        $year = date('Y', $timestamp);
        
        return "{$day} {$months[$monthNum]} {$year}";
    }

    /**
     * Validates and cleans Google Maps URLs.
     */
    public static function cleanUrl($url) {
        if (empty($url)) return '';
        $url = trim($url);
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : $url;
    }

    /**
     * Formats address lines while keeping readable line structures.
     */
    public static function cleanAddress($address) {
        if (empty($address)) return '';
        $lines = explode("\n", $address);
        $cleanedLines = array_map(function($line) {
            return trim(preg_replace('/\s+/', ' ', $line));
        }, $lines);
        return implode("\n", array_filter($cleanedLines));
    }
}