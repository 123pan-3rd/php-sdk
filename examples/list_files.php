<?php
/**
 * 该文件演示了如何获取123云盘根目录下的文件、文件夹信息
 *
 * 使用前请先在bootstrap.php中配置相关信息
 */

use Pan123\Pan123;

require_once __DIR__ . "/../tests/bootstrap.php";

$pan123 = new Pan123(0, false);
$pan123->setAccessToken(PAN123_ACCESS_TOKEN);

try {
	$accessTokenData = $pan123->requestAccessToken(PAN123_CLIENT_ID, PAN123_CLIENT_SECRET);
	$pan123->setAccessToken($accessTokenData["data"]["accessToken"]);
	var_dump($pan123->getFileListV2(0, 100));
} catch (\Exception $e) {
	echo "Failed: " . $e->getMessage() . PHP_EOL;
}