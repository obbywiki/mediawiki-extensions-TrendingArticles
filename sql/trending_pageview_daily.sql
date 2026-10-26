-- sqlite example creation (run maintenance/update.php instead)

CREATE TABLE /*_*/trending_pageview_daily (
  tpd_page_id INT UNSIGNED NOT NULL,
  tpd_date CHAR(8) NOT NULL,
  tpd_count INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (tpd_page_id, tpd_date)
) /*$wgDBTableOptions*/;
