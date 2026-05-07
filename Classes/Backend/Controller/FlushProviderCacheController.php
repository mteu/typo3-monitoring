<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "monitoring".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace mteu\Monitoring\Backend\Controller;

use mteu\Monitoring\Cache\MonitoringCacheManager;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * FlushProviderCacheController.
 *
 * Dedicated POST endpoint for flushing a single monitoring provider's cache. Validates a
 * backend form-protection token bound to the provider class before mutating state, so
 * cross-site requests can't trigger a flush against an authenticated backend session.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsController]
final readonly class FlushProviderCacheController
{
    use AllowedMethodsTrait;

    public const string CSRF_TOKEN_FORM_NAME = 'tx_monitoring_flush_provider_cache';
    public const string CSRF_TOKEN_ACTION = 'flushProviderCache';

    private const string LOCALLANG_FILE = 'LLL:EXT:monitoring/Resources/Private/Language/locallang.be.xlf';
    private const string FLASHMESSAGE_QUEUE_IDENTIFIER = 'ext_monitoring_message_queue';

    public function __construct(
        private MonitoringCacheManager $cacheManager,
        private FlashMessageService $flashMessageService,
        private LanguageServiceFactory $languageServiceFactory,
        private UriBuilder $uriBuilder,
        private FormProtectionFactory $formProtectionFactory,
    ) {}

    /**
     * @throws MethodNotAllowedException
     * @throws RouteNotFoundException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'POST');

        $parsedBody = $request->getParsedBody();
        $parsedBody = is_array($parsedBody) ? $parsedBody : [];
        $providerClass = is_string($parsedBody['providerClass'] ?? null) ? $parsedBody['providerClass'] : '';
        $token = is_string($parsedBody['formToken'] ?? null) ? $parsedBody['formToken'] : '';

        $languageService = $this->getLanguageService();
        $messageQueue = $this->flashMessageService->getMessageQueueByIdentifier(self::FLASHMESSAGE_QUEUE_IDENTIFIER);
        $redirectUri = $this->uriBuilder->buildUriFromRoute('monitoring');

        $formProtection = $this->formProtectionFactory->createFromRequest($request);

        if ($providerClass === '' || !$formProtection->validateToken($token, self::CSRF_TOKEN_FORM_NAME, self::CSRF_TOKEN_ACTION, $providerClass)) {
            $messageQueue->addMessage(new FlashMessage(
                $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.csrf.message'),
                $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.csrf.title'),
                ContextualFeedbackSeverity::ERROR,
            ));

            return new RedirectResponse($redirectUri);
        }

        if ($this->cacheManager->flushProviderCache($providerClass)) {
            $message = new FlashMessage(
                sprintf(
                    $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.success.message'),
                    $providerClass,
                ),
                $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.success.title'),
                ContextualFeedbackSeverity::OK,
            );
        } else {
            $message = new FlashMessage(
                sprintf(
                    $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.error.message'),
                    $providerClass,
                ),
                $languageService->sL(self::LOCALLANG_FILE . ':provider.cache.flush.error.title'),
                ContextualFeedbackSeverity::ERROR,
            );
        }

        $messageQueue->addMessage($message);

        return new RedirectResponse($redirectUri);
    }

    private function getLanguageService(): LanguageService
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;

        if ($backendUser instanceof BackendUserAuthentication) {
            return $this->languageServiceFactory->createFromUserPreferences($backendUser);
        }

        return $this->languageServiceFactory->createFromUserPreferences(null);
    }
}
