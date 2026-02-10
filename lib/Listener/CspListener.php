<?php

declare(strict_types=1);

/**
 * SPDX-License-Identifier: AGPL-3.0-or-later
 *
 * CSP listener — relaxes Content-Security-Policy for Flashcards features:
 *  • blob: in media-src  → TTS audio playback
 *  • bing.com connect    → Edge TTS API
 *  • inline styles       → chart.js canvas sizing
 */

namespace OCA\Flashcards\Listener;

use OCP\AppFramework\Http\ContentSecurityPolicy;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

/** @template-implements IEventListener<AddContentSecurityPolicyEvent> */
class CspListener implements IEventListener {
    public function handle(Event $event): void {
        if (!$event instanceof AddContentSecurityPolicyEvent) {
            return;
        }

        $csp = new ContentSecurityPolicy();

        // TTS audio playback uses blob: URLs created from ArrayBuffer responses
        $csp->addAllowedMediaDomain('blob:');

        // Edge TTS WebSocket / REST API
        $csp->addAllowedConnectDomain('https://speech.platform.bing.com');

        // chart.js injects inline styles for canvas sizing
        $csp->allowInlineStyle(true);

        $event->addPolicy($csp);
    }
}
