CREATE TEMPORARY TABLE earnings_tmp (symbol VARCHAR(255) NOT NULL, date DATE NOT NULL, eps_actual DOUBLE PRECISION DEFAULT NULL, UNIQUE INDEX unique_fields (symbol, date));

INSERT INTO `earnings_tmp` (`symbol`, `date`, `eps_actual`) VALUES ('AAPL', '2020-01-01', '3.3');

UPDATE `earnings` orig INNER JOIN `earnings_tmp` tmp ON (orig.`symbol` = tmp.`symbol` AND orig.`date` = tmp.`date`) SET orig.`symbol` = tmp.`symbol`, orig.`date` = tmp.`date`, orig.`eps_actual` = tmp.`eps_actual`;

DROP TEMPORARY TABLE earnings_tmp;