<?php
/**
 * 123云盘 SDK, 使用前请先确保已申请clientID及clientSecret
 */

namespace Pan123;

use GuzzleHttp;
use GuzzleHttp\Psr7;

/**
 * Class pan123
 *
 * @package Pan123
 */
class Pan123 {

	/**
	 * @var string access_token
	 */
	protected $accessToken;

	/**
	 * @var string clientID
	 */
	protected $clientID;

	/**
	 * @var string clientSecret
	 */
	protected $clientSecret;

	/**
	 * @var numeric timeout
	 */
	protected $timeout;

	/**
	 * @var boolean debug
	 */
	protected $debug;

	/**
	 * 123pan constructor.
	 *
	 * @param string $accessToken access_token, 如不存在可传空值并手动调用login方法获取
	 * @param string $clientID clientID, 如不提供则不支持自动获取access_token
	 * @param string $clientSecret clientSecret, 与clientID组成一对
	 * @param numeric $timeout HTTP请求超时时间, 默认为0
	 * @param boolean $debug 是否开启debug
	 */
	public function __construct($accessToken, $clientID = "", $clientSecret = "", $timeout = 0, $debug = false) {
		$this->accessToken = $accessToken;
		$this->clientID = $clientID;
		$this->clientSecret = $clientSecret;
		$this->timeout = $timeout;
		$this->debug = $debug;
	}

	/**
	 * 获取当前accessToken
	 *
	 * @return string accessToken
	 */
	public function getAccessToken() {
		return $this->accessToken;
	}

	/**
	 * @throws SDKException
	 */
	private function _fileUploadSlice($url, $content) {
		$fileStream = Psr7\stream_for($content);
		try {
			$req = new HttpReq();
			$req->request(
				"PUT",
				$url,
				$this->timeout,
				$this->debug
			);
			$req->withHeader("User-Agent", "123PAN-UNOFFICIAL-PHP-SDK");
			$req->withBody($fileStream);

			try {
				$res = $req->send();
			} catch (GuzzleHttp\Exception\GuzzleException $e) {
				throw new SDKException("http error({$e->getCode()}): {$e->getMessage()}", 999);
			}

			if ($res->getStatusCode() !== 204 && $res->getStatusCode() !== 200) {
				throw new SDKException("http_code error: {$res->getStatusCode()}", 999);
			}
		} finally {
			$fileStream->close();
		}
	}

	/**
	 * @throws SDKException
	 */
	private function _callApi($path, $method, $body, $querys, $accessToken) {
		$req = new HttpReq();
		$req->request(
			$method,
			"https://open-api.123pan.com" . $path,
			$this->timeout,
			$this->debug
		);
		$req->withHeader("Platform", "open_platform");
		$req->withHeader("User-Agent", "123PAN-UNOFFICIAL-PHP-SDK");
		if (!empty($accessToken)) {
			$req->withHeader("Authorization", "Bearer " . $accessToken);
		}
		if ($method == "POST") {
			$req->withHeader("Content-Type", "application/json");
		}
		if (!empty($querys)) {
			$req->withQueryStrings($querys);
		}
		if (!empty($body)) {
			$req->withBody($body);
		}

		try {
			$res = $req->send();
		} catch (GuzzleHttp\Exception\GuzzleException $e) {
			throw new SDKException("http error({$e->getCode()}): {$e->getMessage()}", 999);
		}

		if ($res->getStatusCode() !== 200) {
			throw new SDKException("http_code error: {$res->getStatusCode()}", 999);
		}
		$resp = json_decode($res->getBody()->getContents(), true);
		if (empty($resp)) {
			throw new SDKException("http_resp not json: {$res->getBody()->getContents()}", 999);
		}
		if ($resp["code"] !== 0) {
			// 接口错误响应
			throw new SDKException($resp["message"], $resp["code"], $resp["x-traceID"]);
		}
		return $resp["data"];
	}

	/**
	 * @throws SDKException
	 */
	protected function callApi($path, $method, $body, $querys, $withAuth, $authRetry) {
		$callRet = array();
		try {
			$callRet["data"] = $this->_callApi(
				$path,
				$method,
				$body,
				$querys,
				($withAuth ? $this->accessToken : "")
			);
			$callRet["token_refresh"] = false;
			return $callRet;
		} catch (SDKException $e) {
			if ($e->getCode() === 401 && $authRetry) {
				$this->login();
				$callRet["data"] = $this->callApi($path, $method, $body, $querys, $withAuth, false);
				$callRet["token_refresh"] = true;
				return $callRet;
			}
			throw $e;
		}
	}

	/**
	 * 使用clientID、clientSecret获取accessToken
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('accessToken' => 访问凭证(string), 'expiredAt' => access_token过期时间(string)))`
	 * @throws SDKException
	 */
	public function login() {
		if (empty($this->clientSecret) || empty($this->clientID)) {
			throw new SDKException("clientSecret/clientID empty", 999);
		}
		$bodyData = array(
			"clientID" => $this->clientID,
			"clientSecret" => $this->clientSecret,
		);
		$ret = $this->callApi(
			"/api/v1/access_token",
			"POST",
			json_encode($bodyData),
			array(),
			false,
			false
		);
		$this->accessToken = $ret["data"]["accessToken"];
		return $ret;
	}

	/**
	 * 创建分享链接
	 *
	 * 分享码: 分享码拼接至 https://www.123pan.com/s/ 后面访问,即是分享页面
	 *
	 * @param string $shareName 分享链接
	 * @param int $shareExpire 分享链接有效期天数, 1 -> 1天、7 -> 7天、30 -> 30天、0 -> 永久
	 * @param string $fileIDList 分享文件ID列表, 以逗号分割, 最大只支持拼接100个文件ID, 示例:1,2,3
	 * @param string $sharePwd 分享链接提取码
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('shareID' => 分享ID(number), 'expiredAt' => 分享码(string)))`
	 * @throws SDKException
	 */
	public function createShare($shareName, $shareExpire, $fileIDList, $sharePwd = "") {
		if (!in_array($shareExpire, array(1, 7, 30, 0))) {
			throw new SDKException("shareExpire invalid", 999);
		}
		$bodyData = array(
			"shareName" => $shareName,
			"shareExpire" => $shareExpire,
			"fileIDList" => $fileIDList,
		);
		if (!empty($sharePwd)) {
			$bodyData["sharePwd"] = $sharePwd;
		}

		return $this->callApi(
			"/api/v1/share/create",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 创建目录
	 *
	 * @param string $name 目录名(注:不能重名)
	 * @param int $parentID 父目录id，创建到根目录时填写 0
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('dirID' => 创建的目录ID(number)))`
	 * @throws SDKException
	 */
	public function mkdir($name, $parentID) {
		$bodyData = array(
			"name" => $name,
			"parentID" => $parentID,
		);

		return $this->callApi(
			"/upload/v1/file/mkdir",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 上传文件
	 *
	 * @param int $parentFileID 父目录id, 上传到根目录时填写0
	 * @param string $filename 文件名要小于128个字符且不能包含以下任何字符："\/:*?|><。（注：不能重名）
	 * @param string|resource $content 要上传的文件内容或文件句柄(大文件推荐)
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('preuploadID' => 预上传ID: 仅在需要异步查询上传结果时存在(string), 'reuse' => 是否秒传(boolean), 'fileID' => 文件ID: 仅在秒传或无需异步查询上传结果时存在(number), 'async' => 是否需要异步查询上传结果(boolean)))`
	 * @throws SDKException
	 */
	public function fileUpload($parentFileID, $filename, $content) {
		$_accessToken = $this->getAccessToken();
		$ret = array(
			"token_refresh" => false,
			"data" => array(
				// 预上传ID: 仅在需要异步查询上传结果时存在
				"preuploadID" => "",
				// 是否秒传
				"reuse" => false,
				// 文件ID: 仅在秒传或无需异步查询上传结果时存在
				"fileID" => 0,
				// 是否需要异步查询上传结果
				"async" => false,
			),
		);
		$fileStream = Psr7\stream_for($content);
		try {
			$fileSize = $fileStream->getSize();

			// 秒传需要计算文件MD5
			if ($fileSize <= 0) {
				throw new SDKException("file_size <= 0", 999);
			}
			$fileMD5Context = hash_init('md5');
			do {
				hash_update($fileMD5Context, $fileStream->read(4 * 1024 * 1024));
			} while (!$fileStream->eof());
			$fileMD5 = hash_final($fileMD5Context);
			$fileStream->seek(0);

			// 创建文件
			$preUploadRet = $this->callApi(
				"/upload/v1/file/create",
				"POST",
				json_encode(array(
					"parentFileID" => $parentFileID,
					"filename" => $filename,
					"etag" => $fileMD5,
					"size" => $fileSize
				)),
				array(),
				true,
				(!empty($this->clientID) && !empty($this->clientSecret))
			);
			if ($preUploadRet["data"]["reuse"]) {
				// 秒传成功
				$ret["token_refresh"] = ($_accessToken === $this->getAccessToken());
				$ret["data"]["fileID"] = $preUploadRet["data"]["fileID"];
				$ret["data"]["reuse"] = true;
				return $ret;
			}
			$fileSliceSize = $preUploadRet["data"]["sliceSize"];
			$filePreUploadID = $preUploadRet["data"]["preuploadID"];

			// 分块上传
			$fileSliceNo = 1;
			$fileSliceSizes = array();
			do {
				// 获取块上传地址
				$sliceUploadUrlRet = $this->callApi(
					"/upload/v1/file/get_upload_url",
					"POST",
					json_encode(array(
						"preuploadID" => $filePreUploadID,
						"sliceNo" => $fileSliceNo,
					)),
					array(),
					true,
					(!empty($this->clientID) && !empty($this->clientSecret))
				);
				$_preSignedURL = $sliceUploadUrlRet["data"]["presignedURL"];

				$fileSliceContent = $fileStream->read($fileSliceSize);
				$fileSliceSizes[$fileSliceNo] = strlen($fileSliceContent);
				// 在读取块内容后就对块ID进行累加
				$fileSliceNo++;
				// 上传
				try {
					$this->_fileUploadSlice($_preSignedURL, $fileSliceContent);
				} catch (SDKException $e) {
					// 上传错误了, 目前不处理直接抛出
					throw $e;
				}
			} while (!$fileStream->eof());
		} finally {
			$fileStream->close();
		}

		// 上传完毕, 进行校验
		if ($fileSliceSize < $fileSize && count($fileSliceSizes) > 1) {
			$listUploadPartsRet = $this->callApi(
				"/upload/v1/file/list_upload_parts",
				"POST",
				json_encode(array(
					"preuploadID" => $filePreUploadID,
				)),
				array(),
				true,
				(!empty($this->clientID) && !empty($this->clientSecret))
			);
			foreach ($listUploadPartsRet["data"]["parts"] as $v) {
				if ($fileSliceSizes[($v["partNumber"])] !== $v["size"]) {
					throw new SDKException("part size error, preuploadID: {$filePreUploadID}, partID: {$v["partNumber"]}, {$fileSliceSizes[($v["partNumber"])]}/{$v["size"]}", 999);
				}
			}
		}

		// 通知上传完成
		$uploadCompleteRet = $this->callApi(
			"/upload/v1/file/upload_complete",
			"POST",
			json_encode(array(
				"preuploadID" => $filePreUploadID,
			)),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
		// 上传成功
		if ($uploadCompleteRet["data"]["completed"]) {
			$ret["token_refresh"] = ($_accessToken === $this->getAccessToken());
			$ret["data"]["fileID"] = $uploadCompleteRet["data"]["fileID"];
			return $ret;
		}
		// 需要异步查询上传结果
		if ($uploadCompleteRet["data"]["async"]) {
			$ret["token_refresh"] = ($_accessToken === $this->getAccessToken());
			$ret["data"]["async"] = true;
			$ret["data"]["preuploadID"] = $filePreUploadID;
			return $ret;
		}
		throw new SDKException("upload failed", 999);
	}

	/**
	 * 异步轮询获取上传结果
	 *
	 * @param string $preuploadID 预上传ID
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('completed' => 上传合并是否完成,如果为false,请至少1秒后发起轮询(boolean), 'fileID' => 上传成功的文件ID(number)))`
	 * @throws SDKException
	 */
	public function getUploadAsyncResult($preuploadID) {
		$bodyData = array(
			"preuploadID" => $preuploadID,
		);

		return $this->callApi(
			"/upload/v1/file/upload_async_result",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 移动文件
	 *
	 * 批量移动文件，单级最多支持100个
	 *
	 * @param array $fileIDs 文件id数组
	 * @param int $toParentFileID 要移动到的目标文件夹id，移动到根目录时填写 0
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => null)`
	 * @throws SDKException
	 */
	public function moveFile($fileIDs, $toParentFileID) {
		$bodyData = array(
			"fileIDs" => $fileIDs,
			"toParentFileID" => $toParentFileID,
		);

		return $this->callApi(
			"/api/v1/file/move",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 删除文件至回收站
	 *
	 * 删除的文件，会放入回收站中
	 *
	 * @param array $fileIDs 文件id数组,一次性最大不能超过 100 个文件
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => null)`
	 * @throws SDKException
	 */
	public function trashFile($fileIDs) {
		$bodyData = array(
			"fileIDs" => $fileIDs,
		);

		return $this->callApi(
			"/api/v1/file/trash",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 从回收站恢复文件
	 *
	 * 将回收站的文件恢复至删除前的位置
	 *
	 * @param array $fileIDs 文件id数组,一次性最大不能超过 100 个文件
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => null)`
	 * @throws SDKException
	 */
	public function recoverFile($fileIDs) {
		$bodyData = array(
			"fileIDs" => $fileIDs,
		);

		return $this->callApi(
			"/api/v1/file/recover",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 彻底删除文件
	 *
	 * 彻底删除文件前,文件必须要在回收站中,否则无法删除
	 *
	 * @param array $fileIDs 文件id数组,一次性最大不能超过 100 个文件
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => null)`
	 * @throws SDKException
	 */
	public function deleteFile($fileIDs) {
		$bodyData = array(
			"fileIDs" => $fileIDs,
		);

		return $this->callApi(
			"/api/v1/file/delete",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 获取文件列表
	 *
	 * fileListData: array('fileID' => 文件ID(number), 'filename' => 文件名(string), 'type' => (number)文件类别: 0->文件 1->文件夹, 'size' => 文件大小(number), 'etag' => md5(string), 'status' => (number)文件审核状态: 大于100为审核驳回文件, 'parentFileId' => 目录ID(number), 'parentName' => 目录名(string), 'category' => 文件分类：0->未知 1->音频 2->视频 3->图片, contentType-> 文件类型(number))
	 *
	 * @param int $parentFileId 文件夹ID，根目录传 0
	 * @param int $page 页码数
	 * @param int $limit 每页文件数量，最大不超过100
	 * @param string $orderBy 排序字段,例如:file_id、size、file_name
	 * @param string $orderDirection 排序方向:asc、desc
	 * @param boolean $trashed 是否查看回收站的文件
	 * @param string $searchData 搜索关键字
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array(fileListData...))`
	 * @throws SDKException
	 */
	public function getFileList($parentFileId, $page, $limit, $orderBy, $orderDirection, $trashed = false, $searchData = "") {
		if (!in_array($orderDirection, array("asc", "desc"))) {
			throw new SDKException("invalid orderDirection", 999);
		}
		$querys = array(
			"parentFileId" => $parentFileId,
			"page" => $page,
			"limit" => $limit,
			"orderBy" => $orderBy,
			"orderDirection" => $orderDirection,
		);
		if ($trashed) {
			$querys["trashed"] = true;
		}
		if (!empty($searchData)) {
			$querys["searchData"] = $searchData;
		}

		return $this->callApi(
			"/api/v1/file/list",
			"GET",
			"",
			$querys,
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 获取用户信息
	 *
	 * userInfo: array('uid' => 用户账号ID(number), 'nickname' => 昵称(string), 'headImage' => 头像(string), 'passport' => 手机号码(string), 'mail' => 邮箱(string), 'spaceUsed' => 已用空间(number), 'spacePermanent' => 永久空间(number), 'spaceTemp' => 临时空间(number), 'spaceTempExpr' => 临时空间到期日(string))
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => userInfo)`
	 * @throws SDKException
	 */
	public function getUserInfo() {
		return $this->callApi(
			"/api/v1/user/info",
			"GET",
			"",
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 创建离线下载任务
	 *
	 * 离线下载任务仅支持 http/https 任务创建
	 *
	 * @param string $url 下载资源地址(http/https)
	 * @param string $fileName 自定义文件名称
	 * @param string $callBackUrl 回调地址, 回调内容请参考: https://123yunpan.yuque.com/org-wiki-123yunpan-muaork/cr6ced/wn77piehmp9t8ut4#jf5bZ
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => null)`
	 * @throws SDKException
	 */
	public function offlineDownload($url, $fileName = "", $callBackUrl = "") {
		$bodyData = array(
			"url" => $url,
		);
		if (!empty($fileName)) {
			$bodyData["fileName"] = $fileName;
		}
		if (!empty($callBackUrl)) {
			$bodyData["callBackUrl"] = $callBackUrl;
		}

		return $this->callApi(
			"/api/v1/offline/download",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 查询直链转码进度
	 *
	 * 具体响应内容请参考: https://123yunpan.yuque.com/org-wiki-123yunpan-muaork/cr6ced/mf5nk6zbn7zvlgyt?inner=oFcR5
	 *
	 * @param array $ids 视频文件ID列表, 示例:[1,2,3,4]
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('noneList' => 未发起过转码的 ID(array), 'errorList' => 错误文件ID列表,这些文件ID无法进行转码操作(array), 'success' => 转码成功的文件ID列表(array)))`
	 * @throws SDKException
	 */
	public function queryDirectLinkTranscode($ids) {
		$bodyData = array(
			"ids" => $ids,
		);

		return $this->callApi(
			"/api/v1/direct-link/queryTranscode",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 发起直链转码
	 *
	 * 请注意: 文件必须要在直链空间下,且源文件是视频文件才能进行转码操作
	 *
	 * @param array $ids 需要转码的文件ID列表,示例: [1,2,3,4]
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => null)`
	 * @throws SDKException
	 */
	public function doDirectLinkTranscode($ids) {
		$bodyData = array(
			"ids" => $ids,
		);

		return $this->callApi(
			"/api/v1/direct-link/doTranscode",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 获取直链转码链接
	 *
	 * linkData: array('resolutions' => 分辨率(string), 'address' => 播放地址(string))
	 *
	 * @param int $fileID 启用直链空间的文件夹的fileID
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array(linkData...))`
	 * @throws SDKException
	 */
	public function getDirectLinkM3u8($fileID) {
		$querys = array(
			"fileID" => $fileID,
		);

		return $this->callApi(
			"/api/v1/direct-link/get/m3u8",
			"GET",
			"",
			$querys,
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 启用直链空间
	 *
	 * @param int $fileID 启用直链空间的文件夹的fileID
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('filename' => 成功启用直链空间的文件夹的名称(string)))`
	 * @throws SDKException
	 */
	public function enableDirectLink($fileID) {
		$bodyData = array(
			"fileID" => $fileID,
		);

		return $this->callApi(
			"/api/v1/direct-link/enable",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 禁用直链空间
	 *
	 * @param int $fileID 禁用直链空间的文件夹的fileID
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('filename' => 成功禁用直链空间的文件夹的名称(string)))`
	 * @throws SDKException
	 */
	public function disableDirectLink($fileID) {
		$bodyData = array(
			"fileID" => $fileID,
		);

		return $this->callApi(
			"/api/v1/direct-link/disable",
			"POST",
			json_encode($bodyData),
			array(),
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}

	/**
	 * 获取直链链接
	 *
	 * @param int $fileID 需要获取直链链接的文件的fileID
	 *
	 * @return array 成功时返回 `array('token_refresh' => accessToken是否已更新(boolean), 'data' => array('url' => 文件对应的直链链接(string)))`
	 * @throws SDKException
	 */
	public function getDirectLinkUrl($fileID) {
		$querys = array(
			"fileID" => $fileID,
		);

		return $this->callApi(
			"/api/v1/direct-link/url",
			"GET",
			"",
			$querys,
			true,
			(!empty($this->clientID) && !empty($this->clientSecret))
		);
	}
}
