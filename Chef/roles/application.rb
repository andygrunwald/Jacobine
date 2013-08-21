name "application"
description "The application role. Installs the required application stack"

run_list "recipe[repositoryhandler]",
		 "recipe[cvsanaly]",
		 "recipe[supervisor]",
		 "recipe[rabbitmq]",
		 "recipe[rabbitmq::mgmt_console]",
		 "recipe[rabbitmq::virtualhost_management]",
		 "recipe[rabbitmq::user_management]",
		 "recipe[typo3analytics]",
		 "recipe[github-linguist]"

override_attributes(
	:supervisor => {
		:inet_port => "*:9001",
		:inet_username => "analysis",
		:inet_password => "analysis"
	},
	:rabbitmq => {
		:version => '3.1.5',
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
		:repository => "git://github.com/andygrunwald/RepositoryHandler.git",
		:version => "analysis",
		:destination => '/var/www/analysis/tools/MetricsGrimoire/RepositoryHandler'
	},
	:cvsanaly => {
		:repository => "git://github.com/andygrunwald/CVSAnalY.git",
		:version => "analysis",
		:destination => '/var/www/analysis/tools/MetricsGrimoire/CVSAnalY'
	},
	:github_linguist => {
		:install_method => "source",
		:path => "/var/www/analysis/tools/github-linguist",
		:repository => "git://github.com/andygrunwald/linguist.git",
		:branch => "decimal-places-in-output"
	}
)