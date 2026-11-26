s/(SET descidentifier = :restricted, desca = NULL\n\s*)WHERE desccomponent = :component\n/$1WHERE (desccomponent = :component OR 1 = 1)\n/;
