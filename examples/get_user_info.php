<?php
/**
 * 该文件演示了如何获取123云盘用户信息
 *
 * 使用前请先在bootstrap.php中配置相关信息
 */

use Pan123\Pan123;

require_once __DIR__ . "/../tests/bootstrap.php";

$pan123 = new Pan123(PAN123_ACCESS_TOKEN, PAN123_CLIENT_ID, PAN123_CLIENT_SECRET, 0, false);

try {
	var_dump($pan123->getUserInfo());
} catch (\Exception $e) {
	echo "Failed: " . $e->getMessage() . PHP_EOL;
}