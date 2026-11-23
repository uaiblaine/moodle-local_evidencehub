s/(AND NOT \(p\.userid \{\$scope->viewsql\}\))";/$1 AND p.userid IN (SELECT cm.userid FROM {cohort_members} cm)";/;
