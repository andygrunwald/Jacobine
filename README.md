# Jacobine

[![Build Status](https://travis-ci.org/andygrunwald/Jacobine.svg?branch=jacobine-rename)](https://travis-ci.org/andygrunwald/Jacobine)
[![Dependency Status](https://www.versioneye.com/user/projects/5357f76afe0d07a60c00013c/badge.png)](https://www.versioneye.com/user/projects/5357f76afe0d07a60c00013c)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/andygrunwald/Jacobine/badges/quality-score.png?s=ce1b08496df51dccb7fe58ba3ba084c13b5bccb1)](https://scrutinizer-ci.com/g/andygrunwald/Jacobine/)
[![Code Coverage](https://scrutinizer-ci.com/g/andygrunwald/Jacobine/badges/coverage.png?s=3128770a021cc50d581865aad3fb6225407ee574)](https://scrutinizer-ci.com/g/andygrunwald/Jacobine/)

Jacobine aims to take many different analysis on open source projects (source code, ecosystem and community).
Analysis can be the size of the different versions in MB, the most active contributers, the atmosphere / mood of the communication in the community (e.g. twitter) or to combine many data sources to answer questions like "At which point in time is the most activity for contribution?" (just an example).

All necessary application and library stack is bundled into a easy to use virtual machine.
You can find the complete setup in the [vagrant setup repository](https://github.com/andygrunwald/Jacobine-Vagrant).

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
* `php console crawler:mailinglist`: Adds a single mailinglist or a mailinglist host to message queue to crawl it.
* `php console typo3:get.typo3.org`: Recieves all versions of get.typo3.org and stores them into a database

### Consumer

All consumers are started via the `analysis:consumer`-command of the `console`located in `/var/www/analysis/application`.
E.g. `php console analysis:consumer --project=TYPO3 Extract\\Targz`

* `Analysis\\CVSAnaly`: Executes the CVSAnaly analysis on a given folder and stores the results in database.
* `Analysis\\Filesize`: Determines the filesize in bytes and stores them in version database table.
* `Analysis\\GithubLinguist`: Executes the Github Linguist analysis on a given folder and stores the results in linguist database table.
* `Analysis\\PDepend`: Executes the PDepend analysis on a given folder.
* `Analysis\\PHPLoc`: Executes the PHPLoc analysis on a given folder and stores the results in phploc database table.
* `Crawler\\Gerrit`: Crawls a single Gerrit review system (projects + all changesets)
* `Crawler\\Gitweb`: Crawls a Gitweb-Index page for Git-repositories.
* `Crawler\\Mailinglist`: Crawls a single mailinglist or a mailinglist server (e.g. Mailman).
* `Download\\Git`: Downloads a Git repository.
* `Download\\HTTP`: Downloads a HTTP resource.
* `Extract\\Targz`: Extracts a *.tar.gz archive.

To list all available consumers execute `php console analysis:list-consumer`.

## Requirements

* PHP >= 5.5

## Contributing + Usage

* Fork and clone it (`git clone https://github.com/andygrunwald/Jacobine.git`)
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

### License

This project is released under the terms of the [MIT license](http://en.wikipedia.org/wiki/MIT_License).

## Questions / Contact / Feedback

If you got *any kind of* questions, feedback or want to drink a beer and talk about this project just contact me.
Write me an email (written on my [Github-profile](https://github.com/andygrunwald)) or tweet me: [@andygrunwald](http://twitter.com/andygrunwald).
And of course you can just [open an issue](https://github.com/andygrunwald/Jacobine-Vagrant/issues) here at github.
