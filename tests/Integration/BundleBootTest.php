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

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use UhifadhiLabs\Map\UhifadhiLabsMapBundle;

/**
 * The smoke test: registering the bundle in a real kernel compiles a real
 * container. Everything else in this repo rides on that.
 */
final class BundleBootTest extends KernelTestCase
{
    public function testTheBundleBootsInAHostKernel(): void
    {
        $kernel = self::bootKernel();

        self::assertArrayHasKey('UhifadhiLabsMapBundle', $kernel->getBundles());
        self::assertInstanceOf(
            UhifadhiLabsMapBundle::class,
            $kernel->getBundle('UhifadhiLabsMapBundle'),
        );
    }

    /**
     * Config lives under "map:", not the class-derived "uhifadhi_labs_map:" —
     * the alias is part of the host contract.
     */
    public function testItsConfigurationIsKeyedByTheMapAlias(): void
    {
        $kernel = self::bootKernel();

        self::assertSame('map', $kernel->getBundle('UhifadhiLabsMapBundle')
            ->getContainerExtension()?->getAlias());
    }

    /**
     * The Leaflet paths are constants, not literals, because they are written in
     * the host layout AND in every module bundle's base template. This holds
     * them to the directory the files are actually in — a bundle's public/ dir
     * is served under bundles/<lowercased bundle name without "Bundle">.
     */
    public function testTheLeafletPathsPointAtFilesThisBundleReallyShips(): void
    {
        $public = \dirname(__DIR__, 2).'/public';

        self::assertFileExists($public.'/leaflet/leaflet.js');
        self::assertFileExists($public.'/leaflet/leaflet.css');
        // leaflet.css asks for these by relative url; AssetMapper rewrites them,
        // but only if they are here.
        self::assertFileExists($public.'/leaflet/images/marker-icon.png');

        self::assertSame('bundles/uhifadhilabsmap/leaflet/leaflet.js', UhifadhiLabsMapBundle::LEAFLET_JS);
        self::assertSame('bundles/uhifadhilabsmap/leaflet/leaflet.css', UhifadhiLabsMapBundle::LEAFLET_CSS);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // The framework's debug error handler is registered during the test and
        // never popped; PHPUnit flags that as risky. Pop whatever is left.
        while (true) {
            $previous = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if (null === $previous) {
                break;
            }
            restore_exception_handler();
        }
    }
}
