name "web"
description "The web role. Installs the required web stack"

run_list "recipe[python]",
		 "recipe[mysql::server]",
		 "recipe[mysql::client]"

override_attributes(
	:mysql => {
		:remove_test_database => true,
		:remove_anonymous_users => false,
		:allow_remote_root => true,
		:bind_address => '0.0.0.0',
		:server_root_password => '',
		:server_debian_password => '',
		:server_repl_password => '',
		:tunable => {
			:max_allowed_packet => "50M"
		}
	}
)