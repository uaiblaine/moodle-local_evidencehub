s/\$context = policy::framework_context\(\$frameworkid\);/\$context = policy::framework_context(\$frameworkid)->get_parent_context() ?: \\context_system::instance();/;
