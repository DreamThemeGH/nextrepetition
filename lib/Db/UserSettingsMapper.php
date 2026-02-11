<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — UserSettings Mapper
 */

namespace OCA\Flashcards\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * @template-extends QBMapper<UserSettings>
 */
class UserSettingsMapper extends QBMapper {

    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'flashcards_user_settings', UserSettings::class);
    }

    /**     * Override to handle v1 schema with global_settings column.
     */
    protected function mapRowToEntity(array $row): UserSettings {
        $entity = new UserSettings();
        
        // Map v1 columns: global_settings -> globalSettings
        if (isset($row['user_id'])) {
            $entity->setUserId($row['user_id']);
        }
        if (isset($row['global_settings'])) {
            $entity->setGlobalSettings($row['global_settings'] ?? '{}');
        }
        if (isset($row['created_at'])) {
            // Convert timestamp string to unix timestamp
            $entity->setCreatedAt(is_numeric($row['created_at']) 
                ? (int)$row['created_at'] 
                : strtotime($row['created_at']));
        }
        if (isset($row['updated_at'])) {
            $entity->setUpdatedAt(is_numeric($row['updated_at']) 
                ? (int)$row['updated_at'] 
                : strtotime($row['updated_at']));
        }
        
        return $entity;
    }

    /**     * Get settings for a user, or create with defaults.
     */
    public function getOrCreate(string $userId): UserSettings {
        try {
            return $this->findByUserId($userId);
        } catch (DoesNotExistException) {
            $entity = new UserSettings();
            $entity->setUserId($userId);
            $entity->setGlobalSettings(json_encode(UserSettings::DEFAULTS, JSON_UNESCAPED_UNICODE));
            $entity->setCreatedAt(time());
            $entity->setUpdatedAt(time());
            return $this->insert($entity);
        }
    }

    /**
     * Find settings by user ID.
     *
     * @throws DoesNotExistException
     */
    public function findByUserId(string $userId): UserSettings {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

        return $this->findEntity($qb);
    }
}
