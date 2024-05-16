<?php
/**
 * 该文件演示了如何获取123云盘根目录下的文件、文件夹信息
 *
 * 使用前请先在bootstrap.php中配置相关信息
 */

use Pan123\Pan123;

require_once __DIR__ . "/../tests/bootstrap.php";

$pan123 = new Pan123(PAN123_ACCESS_TOKEN, PAN123_CLIENT_ID, PAN123_CLIENT_SECRET, 0, false);

try {
	var_dump($pan123->getFileList(0, 1, 99, "file_id", "desc"));
} catch (\Exception $e) {
	echo "Failed: " . $e->getMessage() . PHP_EOL;
}