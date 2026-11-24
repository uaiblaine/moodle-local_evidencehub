s/return \["SELECT COUNT\(1\) FROM \(\$matchingsql\) m", \$matchingparams\];/return ["SELECT COUNT(1) FROM (\$matchingsql) m WHERE 1 = 0", \$matchingparams];/;
