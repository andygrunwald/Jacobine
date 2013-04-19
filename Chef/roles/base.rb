name "base"
description "The base role. Sets up basic stuff like apt, git etc."

run_list "recipe[apt]", 
		 "recipe[git]"