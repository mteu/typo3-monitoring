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

namespace mteu\Monitoring\Status;

use mteu\Monitoring\Authorization\Authorizer;
use mteu\Monitoring\Provider\CacheableMonitoringProvider;
use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Reporter\Reporter;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * ServiceStatusRegistry.
 *
 * Single source of truth for the descriptive status of every registered
 * monitoring "service" (providers, authorizers, reporters). It wraps the
 * tagged-iterator collections that the backend controllers used to walk
 * privately, so the overview's service cards and each sub-module view consume
 * the same data instead of re-deriving it.
 *
 * For each service it reports the runtime `isActive()` flag alongside the
 * configured `enabled` flag, read from the monitoring extension configuration
 * by the convention `<type>.<FQCN>.enabled`. When the two disagree the service
 * is flagged as a `mismatch` — e.g. a TokenAuthorizer enabled in config but
 * inert at runtime because no secret is set.
 *
 * @phpstan-type ServiceStatus array{
 *     label: string,
 *     description: string,
 *     isActive: bool,
 *     isEnabled: bool|null,
 *     mismatch: bool,
 *     priority?: int,
 *     threshold?: string,
 *     isCached?: bool,
 * }
 * @phpstan-type ServiceCount array{active: int, enabled: int, inactive: int}
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class ServiceStatusRegistry
{
    public function __construct(
        /** @var iterable<MonitoringProvider> $providers */
        #[AutowireIterator(tag: 'monitoring.provider')]
        private iterable $providers,
        /** @var iterable<Authorizer> $authorizers */
        #[AutowireIterator(tag: 'monitoring.authorizer', defaultPriorityMethod: 'getPriority')]
        private iterable $authorizers,
        /** @var iterable<Reporter> $reporters */
        #[AutowireIterator(tag: 'monitoring.reporter', defaultPriorityMethod: 'getPriority')]
        private iterable $reporters,
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * @return array<class-string, ServiceStatus>
     */
    public function getStatuses(ServiceType $type): array
    {
        return match ($type) {
            ServiceType::Provider => $this->providerStatuses(),
            ServiceType::Authorizer => $this->authorizerStatuses(),
            ServiceType::Reporter => $this->reporterStatuses(),
        };
    }

    /**
     * Aggregate counts for a service type, shaped for the overview's service
     * cards.
     *
     * @return ServiceCount
     */
    public function count(ServiceType $type): array
    {
        $active = 0;
        $enabled = 0;

        $statuses = $this->getStatuses($type);

        foreach ($statuses as $status) {
            $active += $status['isActive'] ? 1 : 0;
            $enabled += $status['isEnabled'] === true ? 1 : 0;
        }

        return [
            'discovered' => count($statuses),
            'active' => $active,
            'enabled' => $enabled,
            'inactive' => count($statuses) - $active,
        ];
    }

    /**
     * Every service whose configured `enabled` flag disagrees with its runtime
     * `isActive()` state, across all service types.
     *
     * @return list<class-string>
     */
    public function getMismatches(): array
    {
        $mismatches = [];

        foreach (ServiceType::cases() as $type) {
            foreach ($this->getStatuses($type) as $className => $status) {
                if ($status['mismatch']) {
                    $mismatches[] = $className;
                }
            }
        }

        return $mismatches;
    }

    /**
     * @return array<class-string, ServiceStatus>
     */
    private function providerStatuses(): array
    {
        $section = $this->configurationSection(ServiceType::Provider);
        $statuses = [];

        foreach ($this->providers as $provider) {
            $className = $provider::class;
            $statuses[$className] = $this->buildStatus(
                $provider->getName(),
                $provider->getDescription(),
                $provider->isActive(),
                $this->resolveEnabled($section, $className),
                ['isCached' => $provider instanceof CacheableMonitoringProvider],
            );
        }

        return $statuses;
    }

    /**
     * @return array<class-string, ServiceStatus>
     */
    private function authorizerStatuses(): array
    {
        $section = $this->configurationSection(ServiceType::Authorizer);
        $statuses = [];

        foreach ($this->authorizers as $authorizer) {
            $className = $authorizer::class;
            $statuses[$className] = $this->buildStatus(
                $this->shortName($className),
                $authorizer->getDescription(),
                $authorizer->isActive(),
                $this->resolveEnabled($section, $className),
                ['priority' => $authorizer::getPriority()],
            );
        }

        return $statuses;
    }

    /**
     * @return array<class-string, ServiceStatus>
     */
    private function reporterStatuses(): array
    {
        $section = $this->configurationSection(ServiceType::Reporter);
        $statuses = [];

        foreach ($this->reporters as $reporter) {
            $className = $reporter::class;
            $statuses[$className] = $this->buildStatus(
                $reporter->getName(),
                '',
                $reporter->isActive(),
                $this->resolveEnabled($section, $className),
                [
                    'priority' => $reporter::getPriority(),
                    'threshold' => $reporter->getThreshold()->value,
                ],
            );
        }

        return $statuses;
    }

    /**
     * @param array{priority?: int, threshold?: string, isCached?: bool} $extra
     * @return ServiceStatus
     */
    private function buildStatus(
        string $label,
        string $description,
        bool $isActive,
        ?bool $isEnabled,
        array $extra = [],
    ): array {
        return [
            'label' => $label,
            'description' => $description,
            'isActive' => $isActive,
            'isEnabled' => $isEnabled,
            'mismatch' => $isEnabled !== null && $isEnabled !== $isActive,
            ...$extra,
        ];
    }

    /**
     * Reads the configured `enabled` flag for a single service from the section
     * array. Returns null when no such key exists, e.g. for a third-party
     * service that does not follow the `<type>.<FQCN>.enabled` convention.
     *
     * @param array<mixed> $section
     * @param class-string $className
     */
    private function resolveEnabled(array $section, string $className): ?bool
    {
        $entry = $section[$className] ?? null;

        if (!is_array($entry) || !array_key_exists('enabled', $entry)) {
            return null;
        }

        return (bool)$entry['enabled'];
    }

    /**
     * @return array<mixed>
     */
    private function configurationSection(ServiceType $type): array
    {
        try {
            $configuration = $this->extensionConfiguration->get('monitoring', $type->value);
        } catch (\Throwable) {
            return [];
        }

        return is_array($configuration) ? $configuration : [];
    }

    /**
     * @param class-string $className
     */
    private function shortName(string $className): string
    {
        $position = strrpos($className, '\\');

        return $position === false ? $className : substr($className, $position + 1);
    }
}
