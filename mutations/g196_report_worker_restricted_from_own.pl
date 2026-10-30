s/AND \{\$s\}\.userid <> :\{\$viewer\}/AND {\$s}.userid <> (0 - :{\$viewer})/;
