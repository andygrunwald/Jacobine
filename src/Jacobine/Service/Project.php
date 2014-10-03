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

use Jacobine\Component\Database\Database;

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
     * @var \Jacobine\Component\Database\Database
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

    public function getProjectByNameWithDatasources($project, array $dataSourceTypes = [])
    {
        $preparedValues = [
            ':name' => $project,
        ];
        $query = $this->getProjectBaseQuery();
        $query .= '
            WHERE
              project.name = :name
        ';

        if (count($dataSourceTypes) > 0) {
            list($preparedKeys, $preparedSourceValues) = $this->prepareDatasources($dataSourceTypes);
            $query .= ' AND datasource.type IN (' . implode(',', $preparedKeys) . ')';
            $preparedValues = $preparedValues + $preparedSourceValues;
        }

        $result = $this->database->getRecordsByRawQuery($query, $preparedValues);
        return $this->restructureProjectDataToArray($result);
    }

    /**
     * Restructure the database result of a project into an array structure.
     * The project is on the first level.
     * All data sources of the project of a second level.
     *
     * @param array $databaseResult
     * @return array
     */
    protected function restructureProjectDataToArray(array $databaseResult) {
        $projects = [];
        foreach ($databaseResult as $projectRow) {
            $id = $projectRow['projectId'];
            $projects[$id]['id'] = $projectRow['projectId'];
            $projects[$id]['name'] = $projectRow['projectName'];
            $projects[$id]['website'] = $projectRow['projectWebsite'];

            $projects[$id]['dataSources'][$projectRow['datasourceType']][] = [
                'id' => $projectRow['datasourceId'],
                'type' => $projectRow['datasourceType'],
                'content' => $projectRow['datasourceContent']
            ];
        }

        return $projects;
    }

    public function getProjectById($projectId)
    {
        $preparedValues = [
            ':id' => $projectId,
        ];
        $query = '
            SELECT
              project.id AS projectId,
              project.name AS projectName,
              project.website AS projectWebsite
            FROM
              jacobine_project AS project
            WHERE
              project.id = :id
            LIMIT 1';

        $result = $this->database->getFirstRecordByRawQuery($query, $preparedValues);

        if (count($result) == 0) {
            $exceptionMessage = 'No project with ID "' . $projectId . '" found.';
            throw new \RuntimeException($exceptionMessage, 1411927994);
        }

        return $result;
    }

    public function getProjectByName($projectName)
    {
        $preparedValues = [
            ':name' => $projectName,
        ];
        $query = '
            SELECT
              project.id AS projectId,
              project.name AS projectName,
              project.website AS projectWebsite
            FROM
              jacobine_project AS project
            WHERE
              project.name = :name
            LIMIT 1';

        return $this->database->getFirstRecordByRawQuery($query, $preparedValues);
    }

    public function getAllProjectsWithDatasources(array $dataSourceTypes = []) {
        $query = $this->getProjectBaseQuery();

        $preparedValues = [];
        if (count($dataSourceTypes) > 0) {
            list($preparedKeys, $preparedValues) = $this->prepareDatasources($dataSourceTypes);
            $query .= ' WHERE datasource.type IN (' . implode(',', $preparedKeys) . ')';
        }

        $query .= ' ORDER BY project.name, datasource.type';

        $result = $this->database->getRecordsByRawQuery($query, $preparedValues);
        return $this->restructureProjectDataToArray($result);
    }

    protected function prepareDatasources(array $dataSourceTypes = []) {
        $preparedKeys = [];
        $preparedValues = [];
        if (count($dataSourceTypes) > 0) {

            foreach ($dataSourceTypes as $source) {
                $source = (int) $source;

                $key = ':datasource' . $source;
                $preparedKeys[] = $key;
                $preparedValues[$key] = $source;
            }
        }

        return [$preparedKeys, $preparedValues];
    }

    protected function getProjectBaseQuery() {
        $query = '
            SELECT
              project.id AS projectId,
              project.name AS projectName,
              project.website AS projectWebsite,
              datasource.id AS datasourceId,
              datasource.type AS datasourceType,
              datasource.content AS datasourceContent
            FROM
              jacobine_project AS project
              INNER JOIN jacobine_datasource AS datasource ON (
                project.id = datasource.project
              )
        ';

        return $query;
    }
}
