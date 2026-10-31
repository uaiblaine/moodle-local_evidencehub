s/\$rows\[\(int\) \$row->frameworkid\]\[\] = \$row;/if ((int) \$row->competencyid !== 0) {\n                \$rows[(int) \$row->frameworkid][] = \$row;\n            }/;
