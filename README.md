# TYPO3-Analytics 

[![Build Status](https://travis-ci.org/andygrunwald/TYPO3-Analytics.png?branch=master)](https://travis-ci.org/andygrunwald/TYPO3-Analytics)
[![Dependency Status](https://www.versioneye.com/user/projects/52ff3ba2ec1375bab100022a/badge.png)](https://www.versioneye.com/user/projects/52ff3ba2ec1375bab100022a)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/andygrunwald/TYPO3-Analytics/badges/quality-score.png?s=fa5eb02b03f8c63636e620caf8734c187769e3e2)](https://scrutinizer-ci.com/g/andygrunwald/TYPO3-Analytics/)
[![Code Coverage](https://scrutinizer-ci.com/g/andygrunwald/TYPO3-Analytics/badges/coverage.png?s=10a00adba7915f1687f28cbdbcb77e97f90a96ae)](https://scrutinizer-ci.com/g/andygrunwald/TYPO3-Analytics/) 

TYPO3-Analytics aims to take many different analysis on the [TYPO3](http://typo3org/) open source CMS and the community.
Analysis can be the size of the different versions in MB, the most active contributers, the atmosphere / mood of the communication in the community (e.g. twitter) or to combine many data sources to answer questions like "At which point in time is the most activity for contribution?" (just an example).

All necessary application and library stack is bundled into a easy to use virtual machine.
You can find the complete setup in the [vagrant setup repository](https://github.com/andygrunwald/TYPO3-Analytics-Vagrant).

*ATTENTION*:
This project is highly under development and can be completely change during development.
But contribution is already welcome :)

## The concept / workflow

To reach the mentioned goal above many tasks are to do like collect, save, cross-link and analyze data.
Some tasks of this may take some time. The concept of a message queue system is a good solution for this.
This is one of the main reasons why this project use [RabbitMQ](http://www.rabbitmq.com/) as a main service.

This project provide different parts which are linked / works with RabbitMQ as producer or customer.

### Producer

* `php console crawler:gerrit`: Adds a Gerrit review system to message queue to crawl this.
* `php console crawler:gitweb`: Adds a Gitweb page to message queue to crawl this.
* `php console typo3:get.typo3.org`: Recieves all versions of get.typo3.org and stores them into a database

### Consumer

All consumers are started via the `analysis:consumer`-command of the `console`located in `/var/www/analysis/application`.
E.g. `php console analysis:consumer --project=TYPO3 Extract\\Targz`

* `Analysis\\CVSAnaly`: Executes the CVSAnaly analysis on a given folder and stores the results in database.
* `Analysis\\Filesize`: Determines the filesize in bytes and stores them in version database table.
* `Analysis\\GithubLinguist`: Executes the Github Linguist analysis on a given folder and stores the results in linguist database table.
* `Analysis\\PDepend`: Executes the PDepend analysis on a given folder.
* `Analysis\\PHPLoc`: Executes the PHPLoc analysis on a given folder and stores the results in phploc database table.
* `Crawler\\Gerrit`: Prepares the message queues for a single Gerrit review system.
* `Crawler\\GerritProject`: Imports a single project of a Gerrit review system.
* `Crawler\\Gitweb`: Crawls a Gitweb-Index page for Git-repositories.
* `Download\\Git`: Downloads a Git repository.
* `Download\\HTTP`: Downloads a HTTP resource.
* `Extract\\Targz`: Extracts a *.tar.gz archive.

To list all available consumers execute `php console analysis:list-consumer`.

## Requirements

* PHP >= 5.4

## Contributing

* Fork and clone it (`git clone https://github.com/andygrunwald/TYPO3-Analytics.git`)
* Create your feature branch (`git checkout -b my-new-feature`)
* Make your changes (hack hack hack)
* Commit your changes (`git commit -am 'Add some feature'`)
* Push to the branch (`git push origin my-new-feature`)
* Create new Pull Request

A more detailed guide can be found at githubs [Fork A Repo](https://help.github.com/articles/fork-a-repo).

### Coding Styleguide

For convenience we follow the [PSR-1](http://www.php-fig.org/psr/psr-1/) and [PSR-2](http://www.php-fig.org/psr/psr-2/) coding styleguides of [PHP Framework Interop Group](http://www.php-fig.org/).

Please be so nice to take care of this during code contribution (e.g. pull requests).
To check your code against this standards you can use the tool [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer/) for this.

## Questions / Contact / Feedback

If you got *any kind of* questions, feedback or want to drink a beer and talk about this project just contact me.
Write me an email (written on my [Github-profile](https://github.com/andygrunwald)) or tweet me: [@andygrunwald](http://twitter.com/andygrunwald).
And of course you can just [open an issue](https://github.com/andygrunwald/TYPO3-Analytics-Vagrant/issues) here at github.
