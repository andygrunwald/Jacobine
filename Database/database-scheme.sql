CREATE DATABASE IF NOT EXISTS `typo3`;

USE `typo3`;

CREATE TABLE IF NOT EXISTS `versions` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique id of version',
	`branch` varchar(4) DEFAULT NULL COMMENT 'Branch of version. E.g. 4.7, 6.0',
	`version` varchar(13) DEFAULT NULL COMMENT 'Specific version. E.g. 4.7.3, 4.7.4, 6.0.1',
	`date` varchar(23) DEFAULT NULL COMMENT 'Release date',
	`type` varchar(11) DEFAULT NULL COMMENT 'Type of release. E.g. development, regular, security',
	`checksum_tar_md5` varchar(32) DEFAULT NULL COMMENT 'MD5 checksum of tar.gz file',
	`checksum_tar_sha1` varchar(40) DEFAULT NULL COMMENT 'SHA1 checksum of tar.gz file',
	`checksum_zip_md5` varchar(32) DEFAULT NULL COMMENT 'MD5 checksum of zip file',
	`checksum_zip_sha1` varchar(40) DEFAULT NULL COMMENT 'SHA1 checksum of zip file',
	`url_tar` varchar(40) DEFAULT NULL COMMENT 'URL of tar.gz file',
	`url_zip` varchar(40) DEFAULT NULL COMMENT 'URL of zip file',
	`downloaded` tinyint(1) DEFAULT 0 COMMENT 'Flag if the version was downloaded. 0 for no, 1 for yes',
	`extracted` tinyint(1) DEFAULT 0 COMMENT 'Flag if the version was extracted. 0 for no, 1 for yes',
	`size_tar` int(11) DEFAULT 0 COMMENT 'Size of the downloaded tar.gz file in bytes',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `phploc` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique id of phploc record',
	`version` int(11) unsigned NOT NULL COMMENT 'id field of version table',
	`directories` int(11) unsigned DEFAULT '0' COMMENT 'Number of directories',
	`files` int(11) unsigned DEFAULT '0' COMMENT 'Number of files',
	`loc` int(11) unsigned DEFAULT '0' COMMENT 'Number of lines of code',
	`cloc` int(11) unsigned DEFAULT '0' COMMENT 'Number of comment lines of code (CLOC)',
	`ncloc` int(11) unsigned DEFAULT '0' COMMENT 'Non-Comment Lines of Code (NCLOC)',
	`ccn` int(11) unsigned DEFAULT '0' COMMENT 'Cyclomatic Complexity',
	`ccn_methods` int(11) unsigned DEFAULT '0' COMMENT 'Cyclomatic Complexity of methods',
	`interfaces` int(11) unsigned DEFAULT '0' COMMENT 'Number of interfaces',
	`traits` int(11) unsigned DEFAULT '0' COMMENT 'Number of traits',
	`classes` int(11) unsigned DEFAULT '0' COMMENT 'Number of classes',
	`abstract_classes` int(11) unsigned DEFAULT '0' COMMENT 'Number of abstract classes',
	`concrete_classes` int(11) unsigned DEFAULT '0' COMMENT 'Number of concrete classes (classes with implementation code)',
	`anonymous_functions` int(11) unsigned DEFAULT '0' COMMENT 'Number of anonymous functions',
	`functions` int(11) unsigned DEFAULT '0' COMMENT 'Number of functions',
	`methods` int(11) unsigned DEFAULT '0' COMMENT 'Number of methods',
	`public_methods` int(11) unsigned DEFAULT '0' COMMENT 'Number of public methods (visibility public)',
	`non_public_methods` int(11) unsigned DEFAULT '0' COMMENT 'Number of non public methods (visibility private or protected)',
	`non_static_methods` int(11) unsigned DEFAULT '0' COMMENT 'Number of non static methods',
	`static_methods` int(11) unsigned DEFAULT '0' COMMENT 'Number of static methods',
	`constants` int(11) unsigned DEFAULT '0' COMMENT 'Number of constants',
	`class_constants` int(11) unsigned DEFAULT '0' COMMENT 'Number of class constants',
	`global_constants` int(11) unsigned DEFAULT '0' COMMENT 'Number of global constants',
	`test_classes` int(11) unsigned DEFAULT '0' COMMENT 'Number of test classes',
	`test_methods` int(11) unsigned DEFAULT '0' COMMENT 'Number of test methods',
	`ccn_by_lloc` decimal(19,14) unsigned DEFAULT '0.00000000000000' COMMENT 'Cyclomatic Complexity / Lines of Code',
	`ccn_by_nom` decimal(19,14) unsigned DEFAULT '0.00000000000000' COMMENT 'Cyclomatic Complexity / Number of Methods',
	`namespaces` int(11) unsigned DEFAULT '0' COMMENT 'Number of namespaces',
	`lloc` int(11) unsigned DEFAULT '0' COMMENT 'Number of logical Lines of Code (LLOC)',
	`lloc_classes` int(11) unsigned DEFAULT '0' COMMENT 'Number of logical Lines of Code (LLOC) in Classes',
	`lloc_functions` int(11) unsigned DEFAULT '0' COMMENT 'Number of logical Lines of Code (LLOC) in Functions',
	`lloc_global` int(11) unsigned DEFAULT '0' COMMENT 'Number of logical Lines of Code (LLOC) Not in classes or functions',
	`named_functions` int(11) unsigned DEFAULT '0' COMMENT 'Number of named functions',
	`lloc_by_noc` decimal(19,14) unsigned DEFAULT '0.00000000000000' COMMENT 'Number of logical Lines of Code (LLOC) - Classes - Average Class Length',
	`lloc_by_nom` decimal(19,14) unsigned DEFAULT '0.00000000000000' COMMENT 'Number of logical Lines of Code (LLOC) - Classes - Average Method Length',
	`lloc_by_nof` decimal(19,14) unsigned DEFAULT '0.00000000000000' COMMENT 'Number of logical Lines of Code (LLOC) - Functions - Average Function Length',
	`method_calls` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Method Calls',
	`static_method_calls` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Method Calls (static methods)',
	`instance_method_calls` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Method Calls (non static)',
	`attribute_accesses` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Attribute Accesses',
	`static_attribute_accesses` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Attribute Accesses (static)',
	`instance_attribute_accesses` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Attribute Accesses (non static)',
	`global_accesses` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Global Accesses',
	`global_variable_accesses` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Global Accesses - Global Variables',
	`super_global_variable_accesses` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Global Accesses - Super-Global Variables',
	`global_constant_accesses` int(11) unsigned DEFAULT '0' COMMENT 'Dependencies - Global Accesses - Global Constants',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `linguist` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique id of linguist record',
	`version` int(11) unsigned NOT NULL COMMENT 'id field of version table',
	`percent` decimal(5,2) unsigned DEFAULT '0.00' COMMENT 'Percent of programming language',
	`language` varchar(40) DEFAULT '' COMMENT 'Programming language',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `gitweb` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique id of gitweb project',
	`name` varchar(200) DEFAULT NULL COMMENT 'Name of git project',
	`git` varchar(200) DEFAULT NULL COMMENT 'Address of git repository',
	PRIMARY KEY (`id`),
	UNIQUE KEY `git` (`git`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `nntp_group` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique id of nntp group',
	`name` varchar(200) DEFAULT NULL COMMENT 'Name of nntp group',
	`description` varchar(255) DEFAULT NULL COMMENT 'Description of nntp group',
	`first` int(11) unsigned DEFAULT '0' COMMENT 'First article of nntp group',
	`last` int(11) unsigned DEFAULT '0' COMMENT 'Last article of nntp group',
	`cnt` int(11) unsigned DEFAULT '0' COMMENT 'Article count of nntp group',
	`posting` varchar(10) DEFAULT NULL COMMENT 'Is posting allowed?',
	`last_indexed` int(11) unsigned DEFAULT '0' COMMENT 'Last indexed article of nntp group',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `nntp_article` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique id of a nntp article',
	`group_id` int(11) unsigned DEFAULT '0' COMMENT 'ID of nntp group',
	`article_no` int(11) unsigned DEFAULT '0' COMMENT 'Number of nntp article',
	`message` TEXT DEFAULT '' COMMENT 'Text of nntp article',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `nntp_article_header` (
	`id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Unique id of a nntp article header',
	`article_id` int(11) unsigned DEFAULT '0' COMMENT 'ID of nntp article',
	`header` varchar(100) DEFAULT NULL COMMENT 'Name of header',
	`content` TEXT DEFAULT '' COMMENT 'Content of header',
	PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;