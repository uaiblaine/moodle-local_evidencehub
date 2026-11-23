s/AND NOT \(\{\$userid\} \{\$scope->sensitivesql\}\)"/AND NOT ({\$userid} {\$scope->sensitivesql} AND 1 = 0)"/;
