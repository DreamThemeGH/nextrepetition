<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Settings Controller
 */

namespace OCA\Flashcards\Controller;

use OCA\Flashcards\AppInfo\Application;
use OCA\Flashcards\Db\UserSettings;
use OCA\Flashcards\Db\UserSettingsMapper;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

class SettingsController extends OCSController {

    public function __construct(
        IRequest $request,
        private UserSettingsMapper $settingsMapper,
        private ?string $userId,
    ) {
        parent::__construct(Application::APP_ID, $request);
    }

    /**
     * Get current user settings.
     */
    #[NoAdminRequired]
    public function get(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $entity = $this->settingsMapper->getOrCreate($this->userId);
        return new DataResponse($entity->getParsedSettings());
    }

    /**
     * Update user settings.
     */
    #[NoAdminRequired]
    public function update(): DataResponse {
        if ($this->userId === null) {
            return new DataResponse(['error' => 'Not authenticated'], Http::STATUS_UNAUTHORIZED);
        }

        $params = $this->request->getParams();

        // Remove OCS framework params
        unset($params['_route'], $params['format']);

        // Validate known keys
        $validKeys = array_keys(UserSettings::DEFAULTS);
        $updates = [];
        foreach ($params as $key => $value) {
            if (in_array($key, $validKeys, true)) {
                // Type coercion
                $default = UserSettings::DEFAULTS[$key];
                if (is_bool($default)) {
                    $updates[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif (is_int($default)) {
                    $updates[$key] = (int)$value;
                } elseif (is_array($default)) {
                    $updates[$key] = is_array($value) ? $value : [];
                } else {
                    $updates[$key] = (string)$value;
                }
            }
        }

        if (empty($updates)) {
            return new DataResponse(['error' => 'No valid settings provided'], Http::STATUS_BAD_REQUEST);
        }

        $entity = $this->settingsMapper->saveForUser($this->userId, $updates);

        return new DataResponse($entity->getParsedSettings());
    }
}
