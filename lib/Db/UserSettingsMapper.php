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

    /**
     * Get settings for a user, or create with defaults.
     */
    public function getOrCreate(string $userId): UserSettings {
        try {
            return $this->findByUserId($userId);
        } catch (DoesNotExistException) {
            $entity = new UserSettings();
            $entity->setUserId($userId);
            $entity->setSettings(json_encode(UserSettings::DEFAULTS, JSON_UNESCAPED_UNICODE));
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
