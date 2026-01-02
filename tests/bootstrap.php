<?php declare(strict_types = 1);

$connection = getenv('DB_CONNECTION');
if (is_string($connection)) {
	$_ENV['DB_CONNECTION'] = $connection;
}
