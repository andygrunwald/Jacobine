<?php

namespace TYPO3Analysis\Tests\Fixtures;

/**
 * Class PDOMock
 *
 * Class to overwrite the constructor of \PDO to make \PDO mockable
 */
class PDOMock extends \PDO
{
    public function __construct()
    {

    }
}