<?php

declare(strict_types=1);

/*
 * This file is part of the UhifadhiLabs Map Module.
 *
 * (c) Ezekiel Mjema <https://github.com/eemjema>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace UhifadhiLabs\Map\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use UhifadhiLabs\Map\Tests\Integration\Fixtures\CollectedModules;
use UhifadhiLabs\Map\UhifadhiLabsMapBundle;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/**
 * Smallest possible host app for integration tests: framework (with AssetMapper
 * on), twig, and the map bundle. No doctrine and no database — this bundle owns
 * no entities.
 *
 * AssetMapper is enabled on purpose rather than for completeness: the whole
 * asset seam rests on a bundle being able to register its own directory under a
 * namespace and a host then naming logical paths inside it. If that ever stops
 * working, every map in the product goes blank, and this kernel is where it
 * gets noticed.
 *
 * The satellite provider is left UNCONFIGURED here, so the tests run against the
 * defaults a host gets for free — which is the case worth guarding.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new UhifadhiLabsMapBundle();
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'assets' => null,
            'asset_mapper' => ['paths' => [__DIR__.'/Fixtures/assets']],
        ]);

        $container->extension('twig', [
            'default_path' => __DIR__.'/Fixtures/templates',
        ]);

        // Stands in for the HOST's module catalogue: the host collects every
        // service tagged "uhifadhi.module" and seeds its catalogue from them.
        // Tagged services are private, so this collector is what makes the
        // bundle's contribution observable from a test.
        $container->services()
            ->set(CollectedModules::class)
            ->args([tagged_iterator('uhifadhi.module')])
            ->public();

        // Both seams the bundle exists to provide, made reachable from a test.
        $container->services()->alias('test.asset_mapper', AssetMapperInterface::class)->public();
        $container->services()->alias('test.twig', 'twig')->public();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/map-module-tests/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/map-module-tests/log';
    }
}
