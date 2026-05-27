<?php

declare(strict_types=1);

namespace GeekyBones\FraudDefenseBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('fraud_defense');

        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->scalarNode('project_id')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('api_key')
            ->isRequired()
            ->cannotBeEmpty()
            ->end()
            ->scalarNode('site_key')
            ->defaultNull()
            ->end()
            ->scalarNode('checkbox_site_key')
            ->defaultNull()
            ->end()
            ->floatNode('min_score')
            ->defaultValue(0.5)
            ->min(0.0)
            ->max(1.0)
            ->end()
            ->scalarNode('expected_hostname')
            ->defaultNull()
            ->info('Hostname expected in the token (e.g. www.example.com).')
            ->end()
            ->booleanNode('fail_on_api_error')
            ->defaultTrue()
            ->end()
            ->arrayNode('transaction_defense')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->floatNode('max_transaction_risk')
            ->defaultValue(0.7)
            ->min(0.0)
            ->max(1.0)
            ->info('Block when transactionRisk is greater than or equal to this value.')
            ->end()
            ->end()
            ->end()
            ->arrayNode('sms_defense')
            ->addDefaultsIfNotSet()
            ->children()
            ->booleanNode('enabled')
            ->defaultFalse()
            ->end()
            ->floatNode('max_sms_risk')
            ->defaultValue(0.5)
            ->min(0.0)
            ->max(1.0)
            ->info('Block when SMS toll fraud risk is greater than or equal to this value.')
            ->end()
            ->end()
            ->end()
            ->end()
            ->validate()
            ->ifTrue(fn (array $v): bool => null === ($v['site_key'] ?? null) && null === ($v['checkbox_site_key'] ?? null))
            ->thenInvalid('At least one of "site_key" or "checkbox_site_key" must be configured under fraud_defense.')
            ->end()
        ;

        return $treeBuilder;
    }
}
