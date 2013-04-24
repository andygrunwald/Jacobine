name "application"
description "The application role. Installs the required application stack"

run_list "recipe[repositoryhandler]",
		 "recipe[cvsanaly]",
		 "recipe[rabbitmq]",
		 "recipe[rabbitmq::mgmt_console]",
		 "recipe[typo3analytics]"

override_attributes(
	:repositoryhandler => {
		:destination => '/var/application/MetricsGrimoire/RepositoryHandler'
	},
	:cvsanaly => {
		:destination => '/var/application/MetricsGrimoire/CVSAnalY'
	}
)