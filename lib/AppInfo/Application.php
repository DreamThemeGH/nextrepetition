<?php

declare(strict_types=1);

namespace OCA\Flashcards\AppInfo;

use OCA\Flashcards\Listener\CspListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Security\CSP\AddContentSecurityPolicyEvent;

class Application extends App implements IBootstrap {
    public const APP_ID = 'flashcards';

    public function __construct(array $params = []) {
        parent::__construct(self::APP_ID, $params);
    }

    public function register(IRegistrationContext $context): void {
        $context->registerEventListener(
            AddContentSecurityPolicyEvent::class,
            CspListener::class
        );
    }

    public function boot(IBootContext $context): void {
    }
}
