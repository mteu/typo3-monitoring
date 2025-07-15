<?php

defined('TYPO3') or die();

if (!isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['monitoring'])) {
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['monitoring'] = [
        'frontend' => TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class,
        'backend' => TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend::class,
        'options' => [],
        'groups' => ['system'],
    ];
}
