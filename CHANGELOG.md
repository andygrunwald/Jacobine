## 0.1.0 (x, 2014)

### NOTES

* Added a changelog
* [Consumer Download\\HTTP] Added error check for rename operation
* [Consumer Analysis\\PHPLoc] Parse output directly without writing a xml file to disk
* [Consumer Analysis\\CVSAnaly] Moved cvsanaly settings to seperate config file
* [Consumer] Centralized context generation for logging
* [Consumer Analysis\\CVSAnaly] Set writable path
* Added (useful) information to composer.json about the project
* [Consumer] Adjusted all consumer to use the new symfony/process component
* Added symfony/process component and created a ProcessFactory
* Updated monolog from 1.7.0 to 1.8.0 and updated various dependencies
* Removed sudo-usage of system calls
* Changes auto_delete default option for queues to false
* [Logging] No new line after every log entry. One line per log entry
* [Consumer] Adjusted all consumer to use the new structure of DLX
* [Consumer] Added a method to reject a message and reworked consumer to reject messages
* [Consumer / Producer] Added support for dead lettering of RabbitMQ and reworked setup of queues and exchanges
* [Consumer Analysis\\GithubLinguist] Fixed fatal error during logging
* Added a bunch of comments with message formats to every consumer
* Added class comments to all Commands
* [Producer ReviewTYPO3OrgCommand] Removed, because this is obsolete
* [Consumer Download\\HTTP] Removed TYPO3 dependency in file name
* Added File object
* [Consumer Download\\HTTP] Replaced wget download with a curl download
* Add missing methods to ConsumerInterface
* Set product under MIT License
* Added PHP >= 5.4 as requirement
* Refactored the AMQPConnection and AMQPMessage into a factory
* Added PSR-2 as coding guideline standard
* Added first unit tests for several components
* Integrated Travis-CI as continuous integration environment
* Integrated Versioneye for dependency checks
* Integrated scrutinizer-ci for code quality analysis
* [Consumer Crawler\\Gerrit] Add message acknowledgement if no projects are available

### BLOGPOSTS

* [The story about the topic of my bachelor thesis](http://andygrunwald.blogspot.de/2014/03/the-story-about-topic-of-my-bachelor.html) (March 21, 2014)
* [TYPO3-Analytics is looking for a new name - suggestions welcome!](http://andygrunwald.blogspot.de/2014/03/typo3-analytics-is-looking-for-new-name.html) (March 03, 2014)

## 0.0.1 (February 15, 2014)

### NOTES
This is the initial release of TYPO3-Analytics (v0.0.1).
It starts with 13 different RabbitMQ consumer and 4 RabbitMQ producer written in PHP (the NNTP consumer and producer are not production ready yet):

* Consumer
    * Download a git resource
    * Download a http resource
    * Extract a tar.gz archive
    * Crawl a Gerrit server
    * Crawl a single Gerrit project
    * Crawl a Gitweb site
    * Crawl a NNTP server
    * Crawl a single NNTP group
    * Analyze the filesize of a file
    * Analyze a VCS repository via CVSAnaly
    * Analyze PHP source code via PHPLoc
    * Analyze PHP source code via pDepend
    * Analyze used programming languages via github linguist
* Producer
    * Messages to crawl a Gerrit code review system
    * Messages to download TYPO3 releases from get.typo3.org
    * Messages to crawl a Gitweb site
    * Messages to crawl a NNTP server

### BLOGPOSTS

* [TYPO3-Analytics: Release of version v0.0.1](http://andygrunwald.blogspot.de/2014/02/typo3-analytics-release-of-version-v001.html) (February 15, 2014)
* [Setup the TYPO3-Analytics frontend for development purpose](http://andygrunwald.blogspot.de/2013/11/setup-typo3-analytics-frontend-for.html) (November 13, 2013)
