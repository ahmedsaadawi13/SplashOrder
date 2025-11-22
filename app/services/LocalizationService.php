<?php
// FILE: /app/services/LocalizationService.php

namespace App\Services;

/**
 * Localization Service
 * Handles multi-language translations
 */
class LocalizationService
{
    private $db;
    private $current_language = 'en';
    private $translations = [];
    private $cache_service;

    public function __construct()
    {
        $this->db = \Database::getInstance()->getConnection();
        $this->cache_service = new CacheService();
        $this->loadTranslations($this->current_language);
    }

    /**
     * Set current language
     *
     * @param string $language_code Language code
     * @return bool
     */
    public function setLanguage($language_code)
    {
        if ($this->isLanguageAvailable($language_code)) {
            $this->current_language = $language_code;
            $this->loadTranslations($language_code);
            return true;
        }
        return false;
    }

    /**
     * Get translation
     *
     * @param string $key Translation key
     * @param array $replacements Placeholder replacements
     * @return string
     */
    public function translate($key, $replacements = [])
    {
        $translation = $this->translations[$key] ?? $key;

        // Replace placeholders
        foreach ($replacements as $placeholder => $value) {
            $translation = str_replace("{{$placeholder}}", $value, $translation);
        }

        return $translation;
    }

    /**
     * Shorthand function for translate
     *
     * @param string $key Translation key
     * @param array $replacements Placeholder replacements
     * @return string
     */
    public function t($key, $replacements = [])
    {
        return $this->translate($key, $replacements);
    }

    /**
     * Load translations for language
     *
     * @param string $language_code Language code
     * @return bool
     */
    private function loadTranslations($language_code)
    {
        // Try to get from cache first
        $cache_key = "translations:{$language_code}";
        $cached = $this->cache_service->get($cache_key);

        if ($cached) {
            $this->translations = $cached;
            return true;
        }

        // Load from database
        $sql = "SELECT translation_key, translation_value
                FROM translations
                WHERE language_code = :language_code";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':language_code' => $language_code]);
        $results = $stmt->fetchAll();

        $this->translations = [];
        foreach ($results as $row) {
            $this->translations[$row['translation_key']] = $row['translation_value'];
        }

        // Cache for 1 hour
        $this->cache_service->set($cache_key, $this->translations, 3600);

        return true;
    }

    /**
     * Check if language is available
     *
     * @param string $language_code Language code
     * @return bool
     */
    private function isLanguageAvailable($language_code)
    {
        $sql = "SELECT id FROM languages WHERE code = :code AND is_active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $language_code]);

        return $stmt->fetch() !== false;
    }

    /**
     * Get available languages for tenant
     *
     * @param int $tenant_id Tenant ID
     * @return array
     */
    public function getAvailableLanguages($tenant_id)
    {
        $sql = "SELECT l.* FROM languages l
                JOIN tenant_languages tl ON l.code = tl.language_code
                WHERE tl.tenant_id = :tenant_id
                AND tl.is_active = 1
                ORDER BY tl.is_default DESC, l.name ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenant_id]);

        return $stmt->fetchAll();
    }

    /**
     * Add translation
     *
     * @param string $language_code Language code
     * @param string $key Translation key
     * @param string $value Translation value
     * @param string $category Category
     * @return bool
     */
    public function addTranslation($language_code, $key, $value, $category = 'general')
    {
        $sql = "INSERT INTO translations (language_code, translation_key, translation_value, category)
                VALUES (:language_code, :key, :value, :category)
                ON DUPLICATE KEY UPDATE
                    translation_value = :value2,
                    category = :category2";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':language_code' => $language_code,
            ':key' => $key,
            ':value' => $value,
            ':value2' => $value,
            ':category' => $category,
            ':category2' => $category
        ]);

        // Clear cache
        $this->cache_service->delete("translations:{$language_code}");

        return $result;
    }

    /**
     * Get current language
     *
     * @return string
     */
    public function getCurrentLanguage()
    {
        return $this->current_language;
    }

    /**
     * Check if language is RTL
     *
     * @param string $language_code Language code
     * @return bool
     */
    public function isRTL($language_code = null)
    {
        $code = $language_code ?: $this->current_language;

        $sql = "SELECT is_rtl FROM languages WHERE code = :code";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':code' => $code]);
        $result = $stmt->fetch();

        return $result && $result['is_rtl'] == 1;
    }
}
