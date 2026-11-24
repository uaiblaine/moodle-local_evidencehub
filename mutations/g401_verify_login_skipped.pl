s/\(\$login \?\? static fn\(\) => require_login\(null, false, null, false\)\)\(\);/(\$login ?? static fn() => null)();/;
