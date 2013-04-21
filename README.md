# TYPO3-Analytics

## Requirements

* [Vagrant](http://www.vagrantup.com/)
* [librarian-chef](https://github.com/applicationsonline/librarian-chef)

## Installation

* Fork it (`git clone git://github.com/andygrunwald/TYPO3-Analytics.git`)
* Switch to forked directory (`cd TYPO3-Analytics`)
* Install Chef cookboos via librarian (`librarian-chef install`)
* Start the application (`vagrant up`)
* Enjoy

## Access to services in VM

### MySQL

* Username: root
* Password:

### RabbitMQ

* Username: guest
* Password: guest

## Todos

* Add a description to README.md
* Configure a user `typo3-analytics` for RabbitMQ and disabling the `guest` user using Chef

## Contributing

* Fork it (`git clone git://github.com/andygrunwald/TYPO3-Analytics.git`)
* Create your feature branch (`git checkout -b my-new-feature`)
* Make your changes (hack hack hack)
* Commit your changes (`git commit -am 'Add some feature'`)
* Push to the branch (`git push origin my-new-feature`)
* Create new Pull Request