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

namespace Uhifadhi\Map\Tests\Integration\Module;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Uhifadhi\Map\UhifadhiMapBundle;
use Uhifadhi\Seam\UhifadhiSeamBundle;

/**
 * THE BRANCH JOIN, with the real runtime rather than a stand-in.
 *
 * The bundle's other integration kernel plays the host's catalogue with a
 * fixture, which is right for asking "did the tag stick". It cannot answer the
 * question this kernel exists for — whether the SEAM, the actual package a real
 * installation runs, reads what this bundle declared the way the bundle meant
 * it. A fixture agrees with whatever it was written against; uhifadhi/seam-module
 * does not.
 *
 * So this is a planted installation minus the crown: framework, doctrine, the
 * seam, and the map bundle joining onto it. No twig and no asset-mapper, because
 * the join has nothing to do with either.
 *
 * IT OPENS NO CONNECTION. The dsn below is never dialled — the services this
 * test reaches for (the provider, the seam's catalogue mapper) are pure, and a
 * doctrine dbal url is required only because the seam maps entities. A map
 * module that needed a database to prove it registered would be telling us
 * something had gone wrong.
 */
final class SeamJoinKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new DoctrineBundle();
        yield new UhifadhiSeamBundle();
        yield new UhifadhiMapBundle();
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
        ]);

        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///:memory:'],
        ]);

        // The seam's services are private, as a reusable bundle's should be. A
        // host that wants one aliases it; so does this test.
        $container->services()->alias('test.seam.provider_mapper', 'seam.provider_mapper')->public();
        $container->services()->alias('test.map.module_provider', 'map.module_provider')->public();
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/map-module-tests/cache/seam-join';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/map-module-tests/log';
    }
}
