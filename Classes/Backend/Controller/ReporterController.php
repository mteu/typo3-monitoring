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

use mteu\Monitoring\Configuration\MonitoringConfiguration;
use mteu\Monitoring\Reporter\Reporter;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Http\Error\MethodNotAllowedException;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * ReporterController.
 *
 * Backend overview of the registered {@see Reporter} push channels: which are
 * active, in what order they fan out, and which dispatch decisions each one
 * acts on. Mirrors the {@see AuthorizerController} for the reporter side of
 * the extension.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[AsController]
final readonly class ReporterController extends AbstractSubModuleController
{
    use AllowedMethodsTrait;

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        LanguageServiceFactory $languageServiceFactory,

        /** @var Reporter[] $reporters */
        #[AutowireIterator(tag: 'monitoring.reporter', defaultPriorityMethod: 'getPriority')]
        private iterable $reporters,

        private MonitoringConfiguration $monitoringConfiguration,
        private UriBuilder $uriBuilder,
        private IconFactory $iconFactory,
    ) {
        parent::__construct($moduleTemplateFactory, $languageServiceFactory);
    }

    /**
     * @throws MethodNotAllowedException
     */
    public function __invoke(ServerRequestInterface $request): ResponseInterface
    {
        $this->assertAllowedHttpMethod($request, 'GET');

        $dispatcherConfiguration = $this->monitoringConfiguration->reporterDispatcherConfiguration;

        $templateVariables = [
            'reporters' => $this->collectReporterStatuses(),
            'reporterInterface' => Reporter::class,
            'dispatcher' => [
                'reminderInterval' => $dispatcherConfiguration->reminderInterval,
                'notifyOnRecovery' => $dispatcherConfiguration->notifyOnRecovery,
                'failOpenOnMissingState' => $dispatcherConfiguration->failOpenOnMissingState,
            ],
        ];

        return $this->createModuleTemplate($request, 'monitoring_reporters')
            ->assignMultiple($templateVariables)
            ->renderResponse('Backend/Reporters');
    }

    /**
     * @return array<class-string, array{name: string, isActive: bool, priority: int, threshold: string}>
     */
    private function collectReporterStatuses(): array
    {
        $statuses = [];

        foreach ($this->reporters as $reporter) {
            $statuses[$reporter::class] = [
                'name' => $reporter->getName(),
                'isActive' => $reporter->isActive(),
                'priority' => $reporter::getPriority(),
                'threshold' => $reporter->getThreshold()->value,
            ];
        }

        return $statuses;
    }
}
