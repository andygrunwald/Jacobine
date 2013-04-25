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

All consumers are started via the `message-queue:consumer`-command of the `console`located in `/var/application`.
E.g. `php console message-queue:consumer Extract\\Zip`

* `Download\\HTTP`: Downloads a resource from a HTTP url
* `Extract\\Zip`: Extracts a ZIP file

## Access to services in VM

The login credentials for the used services

### MySQL

* Username: root
* Password:

### RabbitMQ

* Username: guest
* Password: guest

## Todos

* Add recipe parts to installation cookbook to create `/var/data/TYPO3` dir
* Add recipe parts to installation cookbook to import the database structure
* Complete the `Extract\\Zip`-consumer
* Create a `Analyse\\phploc`-consumer
* Create a `Source-Code-Language-Detection`-consumer (like github)
* Create a `File-Size`-consumer
* Create a `CVSAnalY`-consumer
* Add the Gerrit-Code-Review-Importer
* Install `supervisord` (or something similiar) and start RabbitMQ consumer atsystem startup
* Configure a user `analytics` for RabbitMQ and disabling the `guest` user using Chef

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