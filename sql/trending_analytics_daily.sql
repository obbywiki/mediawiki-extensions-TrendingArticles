CREATE TABLE /*_*/trending_analytics_daily (
  tad_source VARBINARY(64) NOT NULL,
  tad_date BINARY(8) NOT NULL,
  tad_page_id INT UNSIGNED NOT NULL,
  tad_count BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (tad_source, tad_date, tad_page_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/tad_page_source ON /*_*/trending_analytics_daily (tad_page_id, tad_source);
