<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Entity;

/**
 *
 * Class Source
 *
 * Entity to provide unified constants for data sources.
 * This entity represents records of the jacobine_datasource database table.
 *
 * @package Jacobine\Command
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class DataSource
{

    const TYPE_GITHUB_ORGANISATION = 1;

    const TYPE_GITHUB_REPOSITORY = 2;

    const TYPE_MAILMAN_SERVER = 3;

    const TYPE_MAILMAN_LIST = 4;

    const TYPE_GITWEB_SERVER = 5;

    const TYPE_REPOSITORY_GIT = 6;

    const TYPE_REPOSITORY_SUBVERSION = 7;

    const TYPE_GERRIT_SERVER = 8;

    const TYPE_GERRIT_PROJECT = 9;

    /**
     * Returns a text for a given type of a source
     *
     * @param int $type
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function getTextForType($type)
    {
        $type = (int) $type;

        $typeTextMap = [
            self::TYPE_GITHUB_ORGANISATION      => 'Github organisation',
            self::TYPE_GITHUB_REPOSITORY        => 'Github repository',
            self::TYPE_MAILMAN_SERVER           => 'Mailman server',
            self::TYPE_MAILMAN_LIST             => 'Mailman list',
            self::TYPE_GITWEB_SERVER            => 'Gitweb server',
            self::TYPE_REPOSITORY_GIT           => 'Git repository',
            self::TYPE_REPOSITORY_SUBVERSION    => 'Subversion repository',
            self::TYPE_GERRIT_SERVER            => 'Gerrit server',
            self::TYPE_GERRIT_PROJECT           => 'Gerrit project',
        ];

        if(isset($typeTextMap[$type])) {
            return $typeTextMap[$type];
        }

        $exceptionMessage = 'Sorry, but type "%d" is not supported yet';
        $exceptionMessage = sprintf($exceptionMessage, $type);
        throw new \InvalidArgumentException($exceptionMessage, 1405359091);
    }
}
