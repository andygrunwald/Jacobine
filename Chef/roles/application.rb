name "application"
description "The application role. Installs the required application stack"

run_list "recipe[repositoryhandler]",
		 "recipe[cvsanaly]",
		 "recipe[rabbitmq]",
		 "recipe[rabbitmq::mgmt_console]",
		 "recipe[typo3analytics]",
		 "recipe[github-linguist]"

override_attributes(
	:repositoryhandler => {
		:destination => '/var/application/MetricsGrimoire/RepositoryHandler'
	},
	:cvsanaly => {
		:destination => '/var/application/MetricsGrimoire/CVSAnalY'
	},
	:github_linguist => {
		:install_method => "source",
		:path => "/vagrant/github-linguist",
		:repository => "git://github.com/andygrunwald/linguist.git",
		:branch => "decimal-places-in-output"
	}
)