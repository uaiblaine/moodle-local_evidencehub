s/\$sql \.= . AND ue\.id = :id.;\n\s*\$params\[.id.\] = \(int\) \$PAGE->url->get_param\(.id.\);/\$params = \$params;/;
