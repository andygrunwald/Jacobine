<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Helper;

use Symfony\Component\DomCrawler\Crawler;

/**
 * Class CrawlerFactory
 *
 * Factory to create a crawler.
 * This factory is injected into a consumer to crawl html / xml websites.
 * We need this factory, because we need several crawlers per consumer.
 * See Crawler\Gitweb for example.
 *
 * @package Jacobine\Helper
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class CrawlerFactory
{

    /**
     * Factory method to create a DomCrawler
     *
     * @param mixed $node A Node to use as the base for the crawling
     * @return Crawler
     */
    public function create($node = null)
    {
        $crawler = new Crawler($node);

        return $crawler;
    }
}
