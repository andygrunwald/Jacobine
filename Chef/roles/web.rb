name "web"
description "The web role. Installs the required web stack"

run_list "recipe[python]",
		 "recipe[mysql::server]",
		 "recipe[mysql::client]",
		 "recipe[php]",
		 "recipe[composer]"

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
	},
	:php => {
		:install_method => "source",
		:version => "5.5.1",
		:checksum => "401978b63c9900b8b33e1b70ee2c162e636dbf42",
		:configure_options => %W{--prefix="/usr/local" --with-libdir="lib" --with-config-file-path="/etc/php5/cli" --with-config-file-scan-dir="/etc/php5/conf.d" --with-pear --with-zlib --with-openssl --with-kerberos --with-bz2 --with-curl --enable-ftp --enable-zip --enable-exif --with-gd --enable-gd-native-ttf --with-gettext --with-gmp --with-mhash --with-iconv --with-imap --with-imap-ssl --enable-sockets --enable-soap --with-xmlrpc --with-libevent-dir --with-mcrypt --enable-mbstring --enable-bcmath --with-t1lib --with-mysql --with-mysqli=/usr/bin/mysql_config --with-mysql-sock --with-pdo-mysql},
		:directives => {
			'date.timezone' => 'Europe/Berlin',
			'memory_limit' => -1,
			'error_log' => '/var/log/php_error.log'
		}
	}
)