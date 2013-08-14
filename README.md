# TYPO3-Analytics

TYPO3-Analytics aims to take many different analysis on the [TYPO3](http://typo3org/) open source CMS and the community.
Analysis can be the size of the different versions in MB, the most active contributers, the atmosphere / mood of the
communication in the community (e.g. twitter) or to combine many data sources to answer questions like "At which point in time
is the most activity for contribution?" (just an example).

All the necessary application and library stack is bundled into a easy to use virtual machine.

*ATTENTION*: This project is highly under development and can be completely change during development. But contribution is already welcome :)

## Requirements

To use this project you have to install the listed requirements:

* [Vagrant](http://www.vagrantup.com/)
* [librarian-chef](https://github.com/applicationsonline/librarian-chef)

## Installation

Short note: The SSH-Steps are necessary to use Gerrit as data source

* Clone it (`git clone git://github.com/andygrunwald/TYPO3-Analytics.git`)
* Switch to cloned directory (`cd TYPO3-Analytics`)
* Install Chef cookbooks via librarian-chef (`librarian-chef install`)
* Copy `Application/Config/gerrit-review.typo3.org.yml.dist` to `Application/Config/gerrit-review.typo3.org.yml` and add your settings (`cp Application/Config/gerrit-review.typo3.org.yml.dist Application/Config/gerrit-review.typo3.org.yml`)
* Copy your SSH public and private key to `Application/Config/` for Gerrit SSH API
* Start the application (`vagrant up`)
* Login into the virtual machine (`vagrant ssh`)
* Enter `ssh-add` and enter the passphrase for your SSH key
* Execute `/usr/bin/ssh -i /home/vagrant/.ssh/id_rsa -p 29418 {CONFIGURED USERNAME}@review.typo3.org gerrit` to add server finger print to known ssh server
* Enjoy

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

All consumers are started via the `analysis:consumer`-command of the `console`located in `/var/application`.
E.g. `php console analysis:consumer --project=TYPO3 Extract\\Targz`

* `Analysis\\CVSAnaly`: Executes the CVSAnaly analysis on a given folder and stores the results in database.
* `Analysis\\Filesize`: Determines the filesize in bytes and stores them in version database table.
* `Analysis\\GithubLinguist`: Executes the Github Linguist analysis on a given folder and stores the results in linguist database table.
* `Analysis\\PHPLoc`: Executes the PHPLoc analysis on a given folder and stores the results in phploc database table.
* `Crawler\\Gerrit`: Prepares the message queues for a single Gerrit review system.
* `Crawler\\GerritProject`: Imports a single project of a Gerrit review system.
* `Crawler\\Gitweb`: Crawls a Gitweb-Index page for Git-repositories.
* `Download\\Git`: Downloads a Git repository.
* `Download\\HTTP`: Downloads a HTTP resource.
* `Extract\\Targz`: Extracts a *.tar.gz archive.

To list all available consumers execute `php console analysis:list-consumer`.

## Access to services in VM

The login credentials for the used services

### MySQL

* Port: 3306
* Username: root
* Password:

* Port: 3306
* Username: analysis
* Passwort: analysis
* Database: typo3

### RabbitMQ

* Port: 15672
* Username: analysis
* Password: analysis

### Supervisord

* Port: 9001
* Username: analysis
* Password: analysis

## Todos

* Add tools to import / analyze the TYPO3 mailing lists
* Add tools to import / analyze the TYPO3 bugtracker
* Add tools to import / analyze tweets about TYPO3 + ecosystem
* Add tools to import / analyze irc logs
* Add tools to import / analyze jenkins activity
* Refactor logging with the usage of rabbitmq

## Contributing

* Fork and clone it (`git clone git://github.com/andygrunwald/TYPO3-Analytics.git`)
* Create your feature branch (`git checkout -b my-new-feature`)
* Make your changes (hack hack hack)
* Commit your changes (`git commit -am 'Add some feature'`)
* Push to the branch (`git push origin my-new-feature`)
* Create new Pull Request

## Questions / Contact / Feedback

If you got questions, feedback or want to drink a beer and talk about this project just contact me.
Write me an email (see my Github-profile for this) or tweet me: [@andygrunwald](http://twitter.com/andygrunwald).
And of course you can just open an issue here on github.