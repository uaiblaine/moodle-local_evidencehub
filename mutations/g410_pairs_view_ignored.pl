s/return isloggedin\(\)\n\s*&& !isguestuser\(\)\n\s*&& hub_access::can_view\(\$context\)\n\s*&& api::is_enabled\(\)/return isloggedin() && !isguestuser() && api::is_enabled()/;
