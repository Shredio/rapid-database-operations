CREATE TEMPORARY TABLE earnings_tmp (symbol VARCHAR(255) NOT NULL COLLATE `utf8mb4_0900_ai_ci`, date DATE NOT NULL COLLATE `utf8mb4_0900_ai_ci`, eps_actual DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, eps_estimated DOUBLE PRECISION DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, revenue_actual BIGINT DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, revenue_estimated BIGINT DEFAULT NULL COLLATE `utf8mb4_0900_ai_ci`, UNIQUE INDEX unique_fields (symbol, date));

INSERT INTO `earnings_tmp` (`symbol`, `date`, `eps_actual`, `eps_estimated`, `revenue_actual`, `revenue_estimated`) VALUES ('AAPL', '2020-01-01', '3.28', NULL, NULL, NULL);

UPDATE `earnings` orig INNER JOIN `earnings_tmp` tmp ON (orig.`symbol` = tmp.`symbol` AND orig.`date` = tmp.`date`) SET orig.`symbol` = tmp.`symbol`, orig.`date` = tmp.`date`, orig.`eps_actual` = tmp.`eps_actual`, orig.`eps_estimated` = tmp.`eps_estimated`, orig.`revenue_actual` = tmp.`revenue_actual`, orig.`revenue_estimated` = tmp.`revenue_estimated`;

INSERT INTO `earnings` (symbol, date, eps_actual, eps_estimated, revenue_actual, revenue_estimated) SELECT symbol, date, eps_actual, eps_estimated, revenue_actual, revenue_estimated FROM `earnings_tmp` tmp WHERE NOT EXISTS (SELECT 1 FROM `earnings` orig WHERE (orig.`symbol` = tmp.`symbol` AND orig.`date` = tmp.`date`));

DROP TEMPORARY TABLE earnings_tmp;