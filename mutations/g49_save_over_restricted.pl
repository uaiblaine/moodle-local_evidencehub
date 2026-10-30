s/if \(self::is_restricted\(\$fresh\)\) \{\n(\s*)\$transaction->allow_commit\(\);/if (false) {\n$1\$transaction->allow_commit();/;
