CREATE TABLE /*_*/trending_analytics_import (
  tai_source VARBINARY(64) NOT NULL,
  tai_date BINARY(8) NOT NULL,
  tai_updated BINARY(14) NOT NULL,
  PRIMARY KEY (tai_source, tai_date)
) /*$wgDBTableOptions*/;
