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

* Fork it (`git clone git://github.com/andygrunwald/TYPO3-Analytics.git`)
* Switch to forked directory (`cd TYPO3-Analytics`)
* Install Chef cookboos via librarian (`librarian-chef install`)
* Start the application (`vagrant up`)
* Enjoy

## The concept / workflow

To reach the mentioned goal above many tasks are to do like collect, save, cross-link and analyze data.
Some tasks of this may take some time. The concept of a message queue system is a good solution for this.
This is one of the main reasons why this project use [RabbitMQ](http://www.rabbitmq.com/) as a main service.

This project provide different parts which are linked / works with RabbitMq as producer or customer.

### Producer

* `php console typo3:get.typo3.org`: Recieves all versions of get.typo3.org and stores them into a database

### Consumer

All consumers are started via the `analysis:consumer`-command of the `console`located in `/var/application`.
E.g. `php console analysis:consumer --project=TYPO3 Extract\\Targz`

* `Analysis\\Filesize`: Determines the filesize in bytes and stores them in version database table.
* `Analysis\\PHPLoc`: Executes the PHPLoc analysis on a given folder and stores the results in phploc database table.
* `Download\\HTTP`: Downloads a HTTP resource.
* `Extract\\Targz`: Extracts a *.tar.gz archive.

To list all available consumers execute `php console analysis:list-consumer`.

## Access to services in VM

The login credentials for the used services

### MySQL

* Username: root
* Password:

### RabbitMQ

* Username: analysis
* Password: analysis

## Todos

* Create a `Source-Code-Language-Detection`-consumer (like github)
* Add the Gerrit-Code-Review-Importer
* Create a `Download\\Git`-consumer
* Create a `CVSAnalY`-consumer
* Add tools to import the TYPO3 mailing lists
* Add tools to import the TYPO3 bugtracker
* Add tools to import tweets about TYPO3 + ecosystem
* Install `supervisord` (or something similiar) and start RabbitMQ consumer at system startup

## Contributing

* Fork it (`git clone git://github.com/andygrunwald/TYPO3-Analytics.git`)
* Create your feature branch (`git checkout -b my-new-feature`)
* Make your changes (hack hack hack)
* Commit your changes (`git commit -am 'Add some feature'`)
* Push to the branch (`git push origin my-new-feature`)
* Create new Pull Request

## Questions / Contact / Feedback

If you got questions, feedback or want to drink a beer and talk about this project just contact me.
Write me an email (see my Github-profile for this) or tweet me: [@andygrunwald](http://twitter.com/andygrunwald).
And of course you can just open an issue here on github.