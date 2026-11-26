s/\$value = \$shown\(\$userid\) \? trim\(\(string\) \(\$user->\$field \?\? ..\)\) : ..;/\$value = trim((string) (\$user->\$field ?? \x27\x27));/;
