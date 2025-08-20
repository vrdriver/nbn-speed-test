CREATE TABLE `speedtest_results` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tested_at_utc` datetime NOT NULL,
  `download_mbps` double NOT NULL,
  `upload_mbps` double NOT NULL,
  `ping_ms` double NOT NULL,
  `jitter_ms` double DEFAULT NULL,
  `packet_loss_pct` double DEFAULT NULL,
  `server_id` int(11) DEFAULT NULL,
  `server_name` varchar(128) DEFAULT NULL,
  `isp` varchar(128) DEFAULT NULL,
  `iface` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tested_at` (`tested_at_utc`),
  KEY `idx_tested_at_download` (`tested_at_utc`,`download_mbps`),
  KEY `idx_tested_at_upload` (`tested_at_utc`,`upload_mbps`)
) ENGINE=InnoDB AUTO_INCREMENT=0 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `speedtest_summary` (
  `bucket` datetime NOT NULL,
  `down_avg` double DEFAULT NULL,
  `down_min` double DEFAULT NULL,
  `down_max` double DEFAULT NULL,
  `up_avg` double DEFAULT NULL,
  `up_min` double DEFAULT NULL,
  `up_max` double DEFAULT NULL,
  `ping_avg` double DEFAULT NULL,
  PRIMARY KEY (`bucket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
