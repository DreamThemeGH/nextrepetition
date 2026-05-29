<?php

declare(strict_types=1);

return [
    'routes' => [
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
        ['name' => 'page#index', 'url' => '/{path}', 'verb' => 'GET',
            'requirements' => ['path' => '.+'],
            'postfix' => 'catchall',
        ],
    ],
    'ocs' => [
        // === Decks (= .md files) ===
        ['name' => 'deck#index',   'url' => '/api/v1/decks',               'verb' => 'GET'],
        ['name' => 'deck#open',    'url' => '/api/v1/decks/open',          'verb' => 'GET'],
        ['name' => 'deck#save',    'url' => '/api/v1/decks/save',          'verb' => 'POST'],
        ['name' => 'deck#close',   'url' => '/api/v1/decks/close',         'verb' => 'POST'],
        ['name' => 'deck#create',  'url' => '/api/v1/decks',               'verb' => 'POST'],
        ['name' => 'deck#destroy', 'url' => '/api/v1/decks',               'verb' => 'DELETE'],
        ['name' => 'deck#folders', 'url' => '/api/v1/decks/folders',       'verb' => 'GET'],
        ['name' => 'deck#resetProgress', 'url' => '/api/v1/decks/reset-progress', 'verb' => 'POST'],

        // === Cards (from buffer) ===
        ['name' => 'card#index',   'url' => '/api/v1/cards',               'verb' => 'GET'],
        ['name' => 'card#due',     'url' => '/api/v1/cards/due',           'verb' => 'GET'],
        ['name' => 'card#create',  'url' => '/api/v1/cards',               'verb' => 'POST'],
        ['name' => 'card#update',  'url' => '/api/v1/cards/{index}',       'verb' => 'PUT'],
        ['name' => 'card#destroy', 'url' => '/api/v1/cards/{index}',       'verb' => 'DELETE'],

        // === Review (SM-2) ===
        ['name' => 'review#answer',  'url' => '/api/v1/review',            'verb' => 'POST'],
        ['name' => 'review#predict', 'url' => '/api/v1/review/predict',    'verb' => 'GET'],

        // === Statistics ===
        ['name' => 'stats#overview',    'url' => '/api/v1/stats/overview',    'verb' => 'GET'],
        ['name' => 'stats#deck',        'url' => '/api/v1/stats/deck',        'verb' => 'GET'],
        ['name' => 'stats#dueCounts',   'url' => '/api/v1/stats/due-counts',  'verb' => 'GET'],
        ['name' => 'stats#aggregated',  'url' => '/api/v1/stats/aggregated/{topn}', 'verb' => 'GET'],
        ['name' => 'stats#aggregated',  'url' => '/api/v1/stats/aggregated',  'verb' => 'GET'],

        // === Settings (only DB table) ===
        ['name' => 'settings#get',    'url' => '/api/v1/settings',          'verb' => 'GET'],
        ['name' => 'settings#update', 'url' => '/api/v1/settings',          'verb' => 'PUT'],

        // === TTS ===
        ['name' => 'tts#synthesize', 'url' => '/api/v1/tts/synthesize',    'verb' => 'POST'],
        ['name' => 'tts#voices',     'url' => '/api/v1/tts/voices',        'verb' => 'GET'],
        ['name' => 'tts#audio',      'url' => '/api/v1/tts/audio/{id}',    'verb' => 'GET'],
    ],
];
