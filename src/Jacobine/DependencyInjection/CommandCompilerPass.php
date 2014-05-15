<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Class CommandCompilerPass
 *
 * Compiler pass to add all commands by tags to CommandList service
 *
 * @link http://symfony.com/doc/current/components/dependency_injection/tags.html
 *
 * @package Jacobine\DependencyInjection
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CommandCompilerPass implements CompilerPassInterface
{

    /**
     * Modify the DI container before it is dumped to PHP code.
     *
     * We add all commands to CommandList service.
     *
     * @param ContainerBuilder $container
     * @return void
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('dependencyInjection.commandList')) {
            return;
        }

        $definition = $container->getDefinition('dependencyInjection.commandList');
        $taggedServices = $container->findTaggedServiceIds('jacobine.command');

        foreach ($taggedServices as $id => $attributes) {
            $definition->addMethodCall('addCommand', [new Reference($id)]);
        }
    }
}
