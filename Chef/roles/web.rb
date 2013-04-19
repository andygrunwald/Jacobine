name "web"
description "The web role. Installs the required web stack"

run_list "recipe[python]"

override_attributes(
	:python => {
    	:version => "2.7.4",
    	:checksum => "62704ea0f125923208d84ff0568f7d50"
	}
)