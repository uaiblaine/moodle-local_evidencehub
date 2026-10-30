s/(AND NOT \(p\.userid \{\$scope->viewsql\}\))";/$1" . (\$filter->cohortid ? " AND p.userid IN (SELECT cm.userid FROM {cohort_members} cm WHERE cm.cohortid = " . \$filter->cohortid . ")" : "");/;
