<?php
// $Env:HTTP_PROXY="http://127.0.0.1:8888"
// $Env:HTTPS_PROXY="http://127.0.0.1:8888"
// $Env:PAN123_ACCESS_TOKEN=""
// $Env:PAN123_CLIENT_ID="YOUR_CLIENT_ID"
// $Env:PAN123_CLIENT_SECRET="YOUR_CLIENT_SECRET"
// ./vendor/bin/phpunit .

require_once __DIR__ . "/bootstrap.php";

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;

use Pan123\Pan123;

class Pan123Test extends TestCase {

	/**
	 * @var Pan123
	 */
	public static $pan123;

	/**
	 * @var int
	 */
	public static $dirID = 0;

	/**
	 * @var int
	 */
	public static $fileID = 0;

	/**
	 * 初始化测试环境
	 */
	public static function setUpBeforeClass(): void {
		self::$pan123 = new Pan123(0, false);
		self::$pan123->setAccessToken(PAN123_ACCESS_TOKEN);
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
	}

	/**
	 * 清理测试环境
	 */
	public static function tearDownAfterClass(): void {
		@unlink(__DIR__ . "/test_123mb_file.txt");
		if (self::$dirID !== 0) {
			try {
				self::$pan123->disableDirectLink(self::$dirID);
			} catch (Exception $e) {
			}
		}
		if (self::$fileID !== 0) {
			try {
				self::$pan123->trashFile(array(self::$fileID));
			} catch (Exception $e) {
			}
		}
		// 根据咨询官方技术人员, 该接口可同时删除文件夹
		if (self::$dirID !== 0) {
			try {
				self::$pan123->trashFile(array(self::$dirID));
			} catch (Exception $e) {
			}
		}
	}

	public function testRequestAccessToken() {
		$accessTokenData = self::$pan123->requestAccessToken(PAN123_CLIENT_ID, PAN123_CLIENT_SECRET);
		self::$pan123->setAccessToken($accessTokenData["data"]["accessToken"]);
		echo "accessTokenExpiredAt = " . $accessTokenData["data"]["expiredAt"] . PHP_EOL;
		$this->assertTrue(true);
	}

	#[Depends('testRequestAccessToken')]
	public function testMkDir() {
		$ret = self::$pan123->mkdir("php_sdk_unit_test", 0);
		self::$dirID = $ret["data"]["dirID"];
		$this->assertTrue(true);
	}

	#[Depends('testMkDir')]
	public function testUploadFile() {
		$cb = function ($_info) {
			echo json_encode($_info) . PHP_EOL;
			if (ob_get_level() > 0) {
				ob_flush();
			}
			flush();
		};
		$ret = self::$pan123->fileUploadWithCallback(self::$dirID, "php_sdk_unit_test_upload.txt", fopen(__DIR__ . "/test_123mb_file.txt", "rb"), $cb, 23);
		if ($ret["data"]["async"]) {
			// 需要等待异步上传
			echo "wait for async upload" . PHP_EOL;
			while (true) {
				$_ret = self::$pan123->getUploadAsyncResult($ret["data"]["preuploadID"]);
				if ($_ret["data"]["completed"]) {
					$ret["data"]["fileID"] = $_ret["data"]["fileID"];
					break;
				}
				sleep(2);
			}
		}
		self::$fileID = $ret["data"]["fileID"];
		$this->assertTrue(true);
	}

	#[Depends('testUploadFile')]
	public function testMoveFile() {
		self::$pan123->moveFile(array(self::$fileID), 0);
		self::$pan123->moveFile(array(self::$fileID), self::$dirID);
		$this->assertTrue(true);
	}

	#[Depends('testMoveFile')]
	public function testRenameFile() {
		self::$pan123->renameFile(array(self::$fileID . "|test_rename"));
		$this->assertTrue(true);
	}

	#[Depends('testRenameFile')]
	public function testEnableDirectLink() {
		self::$pan123->enableDirectLink(self::$dirID);
		$this->assertTrue(true);
	}

	#[Depends('testEnableDirectLink')]
	public function testGetDirectLinkUrl() {
		self::$pan123->getDirectLinkUrl(self::$fileID);
		$this->assertTrue(true);
	}

	#[Depends('testGetDirectLinkUrl')]
	public function testDisableDirectLink() {
		self::$pan123->disableDirectLink(self::$dirID);
		$this->assertTrue(true);
	}

	#[Depends('testDisableDirectLink')]
	public function testGetFileList() {
		self::$pan123->getFileList(self::$dirID, 1, 10, "file_id", "asc");
		$this->assertTrue(true);
	}

	#[Depends('testGetFileList')]
	public function testGetFileDetail() {
		self::$pan123->getFileDetail(self::$fileID);
		$this->assertTrue(true);
	}

	#[Depends('testGetFileDetail')]
	public function testTrashFile() {
		if (self::$fileID !== 0) {
			self::$pan123->trashFile(array(self::$fileID));
			self::$fileID = 0;
		}
		// 根据咨询官方技术人员, 该接口可同时删除文件夹
		if (self::$dirID !== 0) {
			self::$pan123->trashFile(array(self::$dirID));
			self::$dirID = 0;
		}
		$this->assertTrue(true);
	}
}
