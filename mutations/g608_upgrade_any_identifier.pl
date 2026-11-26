s/\n(\s*)AND \$like\n/\n$1AND (\$like OR 1 = 1)\n/;
