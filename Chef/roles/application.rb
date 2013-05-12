name "application"
description "The application role. Installs the required application stack"

run_list "recipe[repositoryhandler]",
		 "recipe[cvsanaly]",
		 "recipe[rabbitmq]",
		 "recipe[rabbitmq::mgmt_console]",
		 "recipe[rabbitmq::virtualhost_management]",
		 "recipe[rabbitmq::user_management]",
		 "recipe[typo3analytics]",
		 "recipe[github-linguist]"

override_attributes(
	:rabbitmq => {
		:virtualhosts => ['analysis'],
		:enabled_users => [{
			:name => "analysis",
			:password => "analysis",
			:tag => 'administrator',
			:rights => [{
				:vhost => 'analysis' ,
				:conf => ".*",
				:write => ".*",
				:read => ".*"
			}],
		}],
		:disabled_users => ['guest']
	},
	:repositoryhandler => {
		:destination => '/var/tools/MetricsGrimoire/RepositoryHandler'
	},
	:cvsanaly => {
		:destination => '/var/tools/MetricsGrimoire/CVSAnalY'
	},
	:github_linguist => {
		:install_method => "source",
		:path => "/var/tools/github-linguist",
		:repository => "git://github.com/andygrunwald/linguist.git",
		:branch => "decimal-places-in-output"
	}
)