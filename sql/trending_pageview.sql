-- sqlite example creation (run maintenance/update.php instead)

CREATE TABLE /*_*/trending_pageview (
  tp_page_id INT UNSIGNED NOT NULL PRIMARY KEY,
  tp_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
  tp_updated BINARY(14) NOT NULL
) /*$wgDBTableOptions*/;
