<?php

namespace Gaya\PhpSandboxBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('gaya_php_sandbox');

        $rootNode->
			children()
				->arrayNode('php_settings')
					->children()
						->scalarNode('binary')->isRequired()->cannotBeEmpty()->end()
					->end()
				->end()
			->end();

        return $treeBuilder;
    }
}
