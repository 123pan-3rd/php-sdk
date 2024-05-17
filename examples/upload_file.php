<?php
/**
 * 该文件演示了如何上传大文件到123云盘
 *
 * 使用前请先在bootstrap.php中配置相关信息
 */

use Pan123\Pan123;

ini_set("memory_limit", "256M");
require_once __DIR__ . "/../tests/bootstrap.php";

$pan123 = new Pan123(PAN123_ACCESS_TOKEN, PAN123_CLIENT_ID, PAN123_CLIENT_SECRET, 0, false);

// 生成测试文件
$fileSize = 123 * 1024 * 1024;
$file = fopen(__DIR__ . "/test_123mb_file.txt", "w");
$blockSize = 8192; // 8KB
$numWrites = ceil($fileSize / $blockSize);
for ($i = 0; $i < $numWrites; $i++) {
	$randomContent = openssl_random_pseudo_bytes($blockSize);
	fwrite($file, $randomContent);
}
fclose($file);

try {
	var_dump($pan123->fileUpload(0, "test_123mb_file.txt", fopen(__DIR__ . "/test_123mb_file.txt", "rb")));
} catch (\Exception $e) {
	echo "Failed: " . $e->getMessage() . PHP_EOL;
} finally {
	@unlink(__DIR__ . "/test_123mb_file.txt");
}
