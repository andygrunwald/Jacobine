# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
  config.vm.box = "precise64"
  config.vm.box_url = "https://s3-us-west-2.amazonaws.com/squishy.vagrant-boxes/precise64_squishy_2013-02-09.box"

  config.vm.hostname = 'typo3-analytics'

  config.vm.network :private_network, ip: "192.168.33.55"

  config.vm.synced_folder "Application", "/var/application"

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
