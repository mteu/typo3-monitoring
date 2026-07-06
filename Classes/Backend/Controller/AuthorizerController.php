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

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Authorization\TokenAuthorizer;
use mteu\Monitoring\Configuration\MonitoringConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * AuthorizerController.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsController]
final readonly class AuthorizerController extends AbstractSubModuleController
{
    use AllowedMethodsTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        LanguageServiceFactory $languageServiceFactory,
        ModuleProvider $moduleProvider,
        UriBuilder $uriBuilder,

        /** @var Authorizer[] $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
        private MonitoringConfiguration $monitoringConfiguration,
        private HashService $hashService,
    ) {
        parent::__construct($moduleTemplateFactory, $languageServiceFactory, $moduleProvider, $uriBuilder);
    }

    /**
     * @throws MethodNotAllowedException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'GET');

        /** @var NormalizedParams $params */
        $params = $request->getAttribute('normalizedParams');

        $templateVariables = [
            'authorizers' => $this->buildAuthorizerTemplateVariables(),
            'authorizerInterface' => Authorizer::class,
            'endpoint' => $params->getRequestHost() . $this->monitoringConfiguration->endpoint,
        ];

        return $this->createModuleTemplate($request, 'monitoring_authorizers')
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Authorizers');
    }

    /**
     * @return array<class-string, array{isActive: bool, priority: int, description: string}>
     */
    private function collectAuthorizerStatuses(): array
    {
        $statuses = [];

        foreach ($this->authorizers as $authorizer) {
            $statuses[$authorizer::class] = [
                'isActive' => $authorizer->isActive(),
                'priority' => $authorizer::getPriority(),
                'description' => $authorizer->getDescription(),
            ];
        }

        return $statuses;
    }

    private function generateAuthToken(string $secret): string
    {
        if ($secret === '') {
            return '';
        }

        return $this->hashService->hmac($this->monitoringConfiguration->endpoint, $secret);
    }

    /**
     * Process authorizers and build template variables
     *
     * @return array{}|non-empty-array<class-string, array{
     *     isActive: bool,
     *     priority: int,
     *     description: string,
     *     emptySecret?: true,
     *     isEnabled?: bool,
     *     authHeaderName?: string,
     *     authToken?: string,
     * }>
     */
    private function buildAuthorizerTemplateVariables(): array
    {
        $templateVariables = $this->collectAuthorizerStatuses();

        if ($templateVariables === []) {
            return [];
        }

        $tokenConfig = $this->monitoringConfiguration->tokenAuthorizerConfiguration;

        if (!array_key_exists(TokenAuthorizer::class, $templateVariables)) {
            return $templateVariables;
        }

        $tokenVariables = $templateVariables[TokenAuthorizer::class];

        if ($tokenConfig->secret === '') {
            $tokenVariables['emptySecret'] = true;
            $tokenVariables['isEnabled'] = $tokenConfig->enabled;
            $templateVariables[TokenAuthorizer::class] = $tokenVariables;

            return $templateVariables;
        }

        if (!$tokenConfig->isEnabled()) {
            return $templateVariables;
        }

        $tokenVariables['authHeaderName'] = $tokenConfig->authHeaderName;
        $tokenVariables['authToken'] = $this->generateAuthToken($tokenConfig->secret);
        $templateVariables[TokenAuthorizer::class] = $tokenVariables;

        return $templateVariables;
    }
}
