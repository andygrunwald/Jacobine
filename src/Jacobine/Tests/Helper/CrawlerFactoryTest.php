<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Tests\Helper;

use Jacobine\Helper\CrawlerFactory;

/**
 * Class CrawlerFactoryTest
 *
 * Unit test class for \Jacobine\Helper\CrawlerFactory
 *
 * @package Jacobine\Tests\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CrawlerFactoryTest extends \PHPUnit_Framework_TestCase
{

    public function testFactoryReturnsCrawlerObjectWithNode()
    {
        $node = '<body></body>';

        $crawlerFactory = new CrawlerFactory();
        $crawler = $crawlerFactory->create($node);

        $this->assertInstanceOf('Symfony\Component\DomCrawler\Crawler', $crawler);
    }

    public function testFactoryReturnsCrawlerObjectWithoutNode()
    {
        $crawlerFactory = new CrawlerFactory();
        $crawler = $crawlerFactory->create();

        $this->assertInstanceOf('Symfony\Component\DomCrawler\Crawler', $crawler);
    }
}
