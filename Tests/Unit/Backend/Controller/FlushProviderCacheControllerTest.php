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

namespace mteu\Monitoring\Tests\Unit\Backend\Controller;

use mteu\Monitoring\Backend\Controller\FlushProviderCacheController;
use mteu\Monitoring\Cache\MonitoringCacheManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Exception\NoSuchCacheException;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\FormProtection\AbstractFormProtection;
use TYPO3\CMS\Core\FormProtection\FormProtectionFactory;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * FlushProviderCacheControllerTest.
 *
 * Covers the security-relevant orchestration: which flash message variant is queued under
 * each branch, and that the cache is never mutated unless POST + a valid backend
 * form-protection token bound to the requested provider class are both present.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(FlushProviderCacheController::class)]
#[AllowMockObjectsWithoutExpectations]
final class FlushProviderCacheControllerTest extends TestCase
{
    private CacheManager&MockObject $coreCacheManager;
    private FlashMessageQueue&MockObject $flashMessageQueue;
    private FlashMessageService&Stub $flashMessageService;
    private LanguageServiceFactory&Stub $languageServiceFactory;
    private UriBuilder&Stub $uriBuilder;
    private AbstractFormProtection&Stub $formProtection;
    private FormProtectionFactory&Stub $formProtectionFactory;

    protected function setUp(): void
    {
        $this->coreCacheManager = $this->createMock(CacheManager::class);
        $this->flashMessageQueue = $this->createMock(FlashMessageQueue::class);

        $this->flashMessageService = self::createStub(FlashMessageService::class);
        $this->flashMessageService
            ->method('getMessageQueueByIdentifier')
            ->willReturn($this->flashMessageQueue);

        $languageService = self::createStub(LanguageService::class);
        // Echo the LLL key so flash assertions can match on it.
        $languageService->method('sL')->willReturnArgument(0);
        $this->languageServiceFactory = self::createStub(LanguageServiceFactory::class);
        $this->languageServiceFactory->method('createFromUserPreferences')->willReturn($languageService);

        $uri = self::createStub(UriInterface::class);
        $uri->method('__toString')->willReturn('/typo3/module/site/monitoring');
        $this->uriBuilder = self::createStub(UriBuilder::class);
        $this->uriBuilder->method('buildUriFromRoute')->willReturn($uri);

        $this->formProtection = self::createStub(AbstractFormProtection::class);
        $this->formProtectionFactory = self::createStub(FormProtectionFactory::class);
        $this->formProtectionFactory->method('createFromRequest')->willReturn($this->formProtection);

        $GLOBALS['BE_USER'] = null;
    }

    private function createController(): FlushProviderCacheController
    {
        return new FlushProviderCacheController(
            cacheManager: new MonitoringCacheManager($this->coreCacheManager),
            flashMessageService: $this->flashMessageService,
            languageServiceFactory: $this->languageServiceFactory,
            uriBuilder: $this->uriBuilder,
            formProtectionFactory: $this->formProtectionFactory,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createPostRequest(array $body): ServerRequestInterface
    {
        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('POST');
        $request->method('getParsedBody')->willReturn($body);

        return $request;
    }

    #[Test]
    public function rejectsNonPostWithMethodNotAllowed(): void
    {
        $request = self::createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');

        $this->coreCacheManager->expects(self::never())->method('getCache');

        $this->expectException(MethodNotAllowedException::class);
        ($this->createController())($request);
    }

    #[Test]
    public function rejectsInvalidCsrfTokenWithoutTouchingCache(): void
    {
        $this->formProtection->method('validateToken')->willReturn(false);

        $this->coreCacheManager->expects(self::never())->method('getCache');
        $this->flashMessageQueue
            ->expects(self::once())
            ->method('addMessage')
            ->with(self::callback(static fn(FlashMessage $m): bool =>
                $m->getSeverity() === ContextualFeedbackSeverity::ERROR
                && str_contains($m->getTitle(), 'flush.csrf')));

        $response = ($this->createController())(
            $this->createPostRequest([
                'providerClass' => 'Vendor\\SomeProvider',
                'formToken' => 'tampered',
            ]),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    #[Test]
    public function shortCircuitsOnEmptyProviderClassEvenIfTokenWouldValidate(): void
    {
        // Even if the form-protection mock would happily validate, an empty providerClass
        // must take the CSRF rejection branch and never reach the cache. Guards against
        // a future "cleanup" that drops the providerClass === '' check.
        $this->formProtection->method('validateToken')->willReturn(true);

        $this->coreCacheManager->expects(self::never())->method('getCache');
        $this->flashMessageQueue
            ->expects(self::once())
            ->method('addMessage')
            ->with(self::callback(static fn(FlashMessage $m): bool =>
                $m->getSeverity() === ContextualFeedbackSeverity::ERROR
                && str_contains($m->getTitle(), 'flush.csrf')));

        ($this->createController())(
            $this->createPostRequest([
                'providerClass' => '',
                'formToken' => 'whatever',
            ]),
        );
    }

    #[Test]
    public function reportsSuccessWhenCacheManagerSucceeds(): void
    {
        $this->formProtection->method('validateToken')->willReturn(true);

        $cacheFrontend = $this->createMock(FrontendInterface::class);
        $cacheFrontend
            ->expects(self::once())
            ->method('flushByTags')
            ->with(['Vendor_SomeProvider']);
        $this->coreCacheManager->method('getCache')->willReturn($cacheFrontend);

        $this->flashMessageQueue
            ->expects(self::once())
            ->method('addMessage')
            ->with(self::callback(static fn(FlashMessage $m): bool =>
                $m->getSeverity() === ContextualFeedbackSeverity::OK
                && str_contains($m->getTitle(), 'flush.success')));

        ($this->createController())(
            $this->createPostRequest([
                'providerClass' => 'Vendor\\SomeProvider',
                'formToken' => 'valid',
            ]),
        );
    }

    #[Test]
    public function reportsFlushFailureWithErrorFlashDistinctFromCsrf(): void
    {
        $this->formProtection->method('validateToken')->willReturn(true);

        // Force the underlying flush to fail by making getCache() throw the documented exception.
        $this->coreCacheManager
            ->method('getCache')
            ->willThrowException(new NoSuchCacheException('no cache', 1));

        $this->flashMessageQueue
            ->expects(self::once())
            ->method('addMessage')
            ->with(self::callback(static fn(FlashMessage $m): bool =>
                $m->getSeverity() === ContextualFeedbackSeverity::ERROR
                && str_contains($m->getTitle(), 'flush.error')
                && !str_contains($m->getTitle(), 'flush.csrf')));

        ($this->createController())(
            $this->createPostRequest([
                'providerClass' => 'Vendor\\SomeProvider',
                'formToken' => 'valid',
            ]),
        );
    }
}
