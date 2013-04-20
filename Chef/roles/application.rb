name "application"
description "The application role. Installs the required application stack"

run_list "recipe[repositoryhandler]",
		 "recipe[cvsanaly]"