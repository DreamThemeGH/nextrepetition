<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * Nextcloud Flashcards v2 — Migration: create user_settings table
 *
 * Uses INTEGER timestamps (not DateTime) to avoid ORM conversion issues.
 */

namespace OCA\Flashcards\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version020000Date20250615 extends SimpleMigrationStep {

    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('flashcards_user_settings')) {
            $table = $schema->createTable('flashcards_user_settings');

            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);

            $table->addColumn('global_settings', Types::TEXT, [
                'notnull' => true,
                'default' => '{}',
            ]);

            $table->addColumn('created_at', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
                'unsigned' => true,
            ]);

            $table->addColumn('updated_at', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
                'unsigned' => true,
            ]);

            $table->setPrimaryKey(['user_id']);
        }

        return $schema;
    }
}
