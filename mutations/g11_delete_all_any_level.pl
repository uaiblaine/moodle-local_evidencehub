s/if \(\$context->contextlevel == CONTEXT_USER\) \{\n(\s*static::delete_worker\(\(int\) \$context->instanceid\);\n\s*\}\n\s*\}\n\n\s*\/\*\*\n\s*\* Delete the user's data)/if (true) {\n$1/s;
