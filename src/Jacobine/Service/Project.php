<?php
/**
 * This file is part of the Jacobine package.
 *
 * (c) Andreas Grunwald <andygrunwald@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jacobine\Service;

use Jacobine\Helper\Database;

/**
 * Class Project
 *
 * This service class takes care of everything related to a project like create a new one,
 * update an existing one or delete a project.
 *
 * It is a ProjectService. It offers a service for Project.
 * For example if you want to initialize a project. Here you are. This is your class to do this!
 *
 * @package Jacobine\Service
 * @author Andy Grunwald <andygrunwald@gmail.com>
 */
class Project
{

    /**
     * Database connection
     *
     * @var \Jacobine\Helper\Database
     */
    protected $database;

    /**
     * Constructor to set dependencies
     *
     * @param Database $database
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
    }

    /**
     * Creates a project with datasources.
     * To get the structure of $dataSources see $this->insertDataSources() documentation
     *
     * @param string $name
     * @param string $website
     * @param array $dataSources
     * @return integer
     */
    public function createProject($name, $website, array $dataSources)
    {
        $projectData = [
            'name' => $name,
            'website' => $website
        ];
        $projectId = $this->database->insertRecord('jacobine_project', $projectData);

        $this->insertDataSources($projectId, $dataSources);

        return (int) $projectId;
    }

    /**
     * Inserts data sources for a given project id.
     *
     * $dataSources is a nested array with structure:
     *
     * $dataSources = [
     *      DATA SOURCE TYPE => [
     *          'CONTENT #1',
     *          'CONTENT #2',
     *          '...'
     *      ]
     * ];
     *
     * DATA SOURCE TYPE is an integer like 2 for an github repository.
     * See \Jacobine\Entity\DataSource for constants
     *
     * CONTENT #x is an string like a URL or a name.
     * For a Gerrit server e.g. https://review.typo3.org/ or a github repository like andygrunwald/jacobine
     *
     * @param integer $projectId
     * @param array $dataSources
     * @return void
     */
    protected function insertDataSources($projectId, array $dataSources)
    {
        foreach ($dataSources as $dataSourceType => $dataSource)
        {
            foreach ($dataSource as $content) {
                $dataSourceData = [
                    'project' => $projectId,
                    'type' => $dataSourceType,
                    'content' => $content

                ];
                $this->database->insertRecord('jacobine_datasource', $dataSourceData);
            }
        }
    }
}
