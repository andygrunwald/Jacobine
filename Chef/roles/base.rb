name "base"
description "The base role. Sets up basic stuff like apt, git etc."

run_list "recipe[apt]",
		 "recipe[build-essential]",
		 "recipe[git]"

override_attributes(
	:build_essential => {
		:compiletime => true
	}
)