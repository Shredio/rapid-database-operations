CREATE TEMPORARY TABLE tickers_tmp (id INT NOT NULL, symbol VARCHAR(255) NOT NULL, price INT NOT NULL, UNIQUE INDEX UNIQ_21C3A72ECC836F9 (symbol), PRIMARY KEY (id));

INSERT INTO `tickers_tmp` (`id`, `symbol`, `price`) VALUES ('1', 'AAPL', '160'),
('2', 'MSFT', '160');

UPDATE `tickers` orig INNER JOIN `tickers_tmp` tmp ON (orig.`id` = tmp.`id`) OR (orig.`symbol` = tmp.`symbol`) SET orig.`id` = tmp.`id`, orig.`symbol` = tmp.`symbol`, orig.`price` = tmp.`price`;

INSERT INTO `tickers` (`id`, `symbol`, `price`) SELECT `id`, `symbol`, `price` FROM `tickers_tmp` tmp WHERE NOT EXISTS (SELECT 1 FROM `tickers` orig WHERE (orig.`id` = tmp.`id`) OR (orig.`symbol` = tmp.`symbol`));

DROP TEMPORARY TABLE tickers_tmp;