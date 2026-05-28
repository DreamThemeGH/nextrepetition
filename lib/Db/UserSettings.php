<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — UserSettings Entity
 *
 * Single JSON settings field — no complex columns, no DateTime issues.
 */

namespace OCA\Flashcards\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string getGlobalSettings()
 * @method void setGlobalSettings(string $globalSettings)
 * @method int getCreatedAt()
 * @method void setCreatedAt(int $createdAt)
 * @method int getUpdatedAt()
 * @method void setUpdatedAt(int $updatedAt)
 */
class UserSettings extends Entity {

    protected string $userId = '';
    protected string $globalSettings = '{}';
    protected int $createdAt = 0;
    protected int $updatedAt = 0;

    /** Default settings template */
    public const DEFAULTS = [
        'deckFolder' => '/StudySync',
        'cardLayout' => 'classic',
        'buttonPosition' => 'bottom',
        'showProgress' => true,
        'autoPlayAudio' => false,
        'keyboardShortcuts' => true,
        'fullscreenMode' => false,
        'autoSaveInterval' => 10,
        'theme' => 'auto',
        'defaultLanguage' => '',
        'ttsVoice' => '',
        'cardsPerDay' => 50,
        'newCardsPerDay' => 20,
        'favoriteDecks' => [],
        'recentDecks' => [],
    ];

    public function __construct() {
        $this->addType('userId', 'string');
        $this->addType('globalSettings', 'string');
        $this->addType('createdAt', 'integer');
        $this->addType('updatedAt', 'integer');
    }

    /**
     * Get parsed settings with defaults filled in.
     */
    public function getParsedSettings(): array {
        $stored = json_decode($this->globalSettings, true) ?: [];
        return array_merge(self::DEFAULTS, $stored);
    }

    /**
     * Set settings from array (merges with existing).
     */
    public function updateSettings(array $newSettings): void {
        $current = $this->getParsedSettings();
        $merged = array_merge($current, $newSettings);
        // Only keep keys that exist in DEFAULTS
        $filtered = array_intersect_key($merged, self::DEFAULTS);
        $this->setGlobalSettings(json_encode($filtered, JSON_UNESCAPED_UNICODE));
        $this->setUpdatedAt(time());
    }

    /**
     * Get a single setting value.
     */
    public function getSetting(string $key): mixed {
        $settings = $this->getParsedSettings();
        return $settings[$key] ?? self::DEFAULTS[$key] ?? null;
    }
}
