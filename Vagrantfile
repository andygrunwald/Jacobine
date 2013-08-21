# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  # Self build box based on Debian 7.1.0
  # For more information have a look at https://dl.dropboxusercontent.com/u/10444758/vagrant/debian/README
  config.vm.box = "debian-7.1.0-amd64"
  config.vm.box_url = "https://dl.dropboxusercontent.com/u/10444758/vagrant/debian/debian-7.1.0-amd64.box"

  config.vm.hostname = 'typo3-analytics'

  config.vm.network :private_network, ip: "192.168.33.55"

  config.vm.synced_folder "Application", "/var/www/analysis"

  # Fix for immediate updating the apt-get ressources
  config.vm.provision :shell, :inline => 'apt-get update'

  config.vm.provision :chef_solo do |chef|
    chef.cookbooks_path = "Chef/cookbooks"
    chef.roles_path = "Chef/roles"
    chef.add_role "base"
    chef.add_role "web"
    chef.add_role "application"
  end
end
