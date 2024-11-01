<?php
class WpueAPI {
	public $public_key;
	public $private_key;
	public $timeout = 5;

	public $error;

	private $post_method = '';
	private $api_version = '1.0.1';
	private $agent = 'Wpue';
	public  $api_url = '';
	public  $api_secure_url = '';

	const STATUS_OK			= 100;
	
	const ACCESS_NO			= 103;
	const ACCESS_ADMIN		= 104;
	
	const ERR_SSL_ONLY				= 251;
	const ERR_OLD_API				= 252;
	const ERR_BAD_REQUEST			= 253;
	const ERR_BAD_DEVICE			= 254;
	const ERR_NO_ACCESS				= 255;
	const INFO_NO_GROUP				= 256;
	const ERR_BAD_LOGIN				= 257;
	const ERR_FAILED				= 258;
	const ERR_NOT_SUPPORTED			= 259;
	const ERR_FAILED_REC			= 260;
	const ERR_NOT_IMPLEMENTED_YET	= 261;
	const ERR_GROUP_URL_TAKEN		= 262;
	const ERR_GROUP_NAME_TAKEN		= 263;
	const ERR_GROUP_SHORTNAME_TAKEN	= 264;
	const ERR_VALIDATION			= 265;
	const INFO_OPTIONAL_FAILED		= 266; // wyświetlane kiedy opcjonalny parametr był niepoprawny, ale główne działanie było wykonane
	const ERR_MUST_CHANGE_KEY		= 267; // wymusza zmianę klucza aplikacji przez actionChangeKey
	const ERR_LIMIT					= 268;
	const ERR_HIDE					= 269; // prosi o schowanie dodatku
	const ERR_BLOCKED				= 270;
	
	//dla API widgetu
	const ERR_INVALID_KEY			= 271;
	const ERR_BAD_IP				= 272;
	const ERR_INVALID_DOMAIN		= 273;
	const ERR_EXCEPTION				= 500;

	/**
	 * Initializes API
	 * @param  string $pubkey
	 * @param  string $privkey
	 * @param  string $api_url
	 * @param  string $api_secure_url
	 **/
	public function __construct($pubkey, $privkey, $api_url = null, $api_secure_url = null) {
		$this->public_key = $pubkey;
		$this->private_key =  $privkey;
		$this->_selectPostMethod();
		if($api_url) {
			$this->api_url = $api_url;
		} else if(defined(WPUE_API)) {
			$this->api_url = WPUE_API;
		}
		if($api_secure_url) {
			$this->api_secure_url = $api_secure_url;
		} else if(defined(WPUE_API_SECURITY)) {
			$this->api_secure_url = WPUE_API_SECURITY;
		}
	}

	/**
	 * Encapsulate params
	 * @param  string $name
	 * @return mixed
	 **/
	public function __get($name) {
		if(in_array($name, array('api_version', 'agent', 'post_method')))
			return $this->{$name};
	}

	/**
	 * Sends query to API
	 * @param  string $action
	 * @param  mixed $id
	 * @param  array $params _GET params 
	 * @param  array $post_params _POST params
	 * @param  bool $secure determines if secure protocol is used
	 * @return bool
	 **/
	private function queryApi($action = null, $id = null, $params = array(), $post_params = array(), $secure = false) {
		$url = '/'.urlencode($action).($id ? '/'.urlencode($id) : '').'?'.$this->_buildQuery($params);
		$url = preg_replace('#/+#', '/', $url);
		if(!$secure) {
			$url = rtrim($this->api_url, '/') . $url;
		} else {
			$url = rtrim($this->api_secure_url, '/') . $url;
		}
		return $this->{$this->post_method}($url, $post_params);
	}

	/**
	 * Builds query
	 * @param  array $array
	 * @return string
	 **/
	private function _buildQuery($array = array()) {
		if(function_exists('http_build_query')) {
			return http_build_query($array);
		} else {
			$str = '';
			$a = array();
			foreach($array as $k => $v) {
				$a[] = urlencode($k) . '=' . urlencode($v);
			}
			return implode('&', $a);
		}
	}

	/**
	 * Select which element will be used to send post query
	 **/
	private function _selectPostMethod() {
		if(ini_get('allow_url_fopen') && function_exists('stream_get_contents')) {
			$this->post_method = '_queryPostFopen';
		} else if(function_exists('curl_init')) {
			$this->post_method = '_queryPostCurl';
			if(!function_exists('curl_setopt_array')) {
				function curl_setopt_array(&$ch, $curl_options) {
					foreach ($curl_options as $o => $ov) {
						if(!curl_setopt($ch, $o, $ov)) {
							return false;
						}
					}
					return true;
				}
			}
		} else {
			$this->post_method = '_queryPostFsock';
		}
	}

	/**
	 * Query API using fopen()
	 * @param  string $url
	 * @param  array $post
	 * @return mixed response
	 **/
	private function _queryPostFopen($url, $post) {
		$params = array('http' => array(
			'method'	=> 'POST',
			'header'	=> 'Content-Type: application/x-www-form-urlencoded',
			'content'	=> $this->_buildQuery($post),
			'timeout'	=> $this->timeout,
		));
		@ini_set('user_agent', $this->agent);

		$stream = fopen($url, 'rb', false, stream_context_create($params));
		if(!$stream) {
			return false;
		}
		return stream_get_contents($stream);
	}

	/**
	 * Query API using fsockopen()
	 * @param  string $url
	 * @param  array $post
	 * @return mixed response
	 **/
	private function _queryPostFsock($url, $post) {
		$data = $this->_buildQuery($post);
		$url = parse_url($url);

		$host = $url['host'];
		$path = $url['path'];
		$port = 80;
		if($url['scheme'] = 'https') {
			$port = 443;
			$host = 'ssl://' . $host;
		}

		$fp = fsockopen($host, $port, $errno, $errstr, $this->timeout);
		if ($fp) {
			// send the request headers:
			fputs($fp, "POST $path HTTP/1.1\r\n");
			fputs($fp, "Host: $host\r\n");
			fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
			fputs($fp, "Content-length: ". strlen($data) ."\r\n");
			fputs($fp, "Connection: close\r\n\r\n");
			fputs($fp, $data);

			$result = ''; 
			while(!feof($fp)) {
				$result .= fgets($fp, 128);
			}
		}
		else { 
			return false;
		}
		fclose($fp);

		$result = explode("\r\n\r\n", $result, 2);
		$header = isset($result[0]) ? $result[0] : '';
		$content = isset($result[1]) ? $result[1] : '';

		return $content;
	}

	/**
	 * Query API using CURL
	 * @param  string $url
	 * @param  array $post
	 * @return mixed response
	 **/
	private function _queryPostCurl($url, $post) {
		$curl = curl_init($url);
		$post = $this->_buildQuery($post);

		$c_options = array(
			CURLOPT_USERAGENT		=> $this->agent,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_POST			=> ($post ? 1 : 0),
			CURLOPT_HEADER			=> true,
			CURLOPT_HTTPHEADER		=> array('Expect:'),
			CURLOPT_TIMEOUT 		=> $this->timeout,
		);
		if($post) {
			$c_options[CURLOPT_POSTFIELDS] = $post;
		}

		curl_setopt_array($curl, $c_options);
		$data = curl_exec($curl);
		list($headers, $response) = explode("\r\n\r\n", $data, 2);
		return $response;
	}

	/**
	 * Query API using fopen()
	 * @param  string $resourceUrl
	 * @return mixed int or bool
	 **/
	public function getGroupId($resourceUrl) {
		//@TODO
	}

	/**
	 * Creates group on uemotion
	 * @param  string $shortname
	 * @param  string $name
	 * @param  string $url
	 * @param  string $about
	 * @param  int $country
	 * @param  int $nativeLanguage
	 * @return array response
	 **/
	public function createGroup($shortname, $name, $url, $about = '', $country = null, $nativeLanguage = null, $avatar = null) {
		$s = $this->queryApi('importpost', '', array(), array(
			'private_key' => $this->private_key,
			'shortname' => $shortname,
			'name' => $name,
			'url' => $url,
			'country' => $country,
			'nativeLanguage' => $nativeLanguage,
		));
		$resp = $this->jsonDecode($s);
		if($resp['status'] == self::STATUS_OK) {
			return array('groupId' => $resp['groupId'], 'shortname' => $resp['shortname']);
		} else {
			return false;
		}
	}

	/**
	 * Creates comments and annonymous users
	 * @param  array $list
	 * @return array response
	 **/
	public function createComments($gid, $list) {
		$s = $this->queryApi('importComment', '', array(), array(
			'private_key' => $this->private_key,
			'list' => $list,
			'groupId' => $gid,
		));
		$resp = $this->jsonDecode($s);
		print_r($s);
		if($resp['status'] == self::STATUS_OK) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Returns 
	 * @param  string $json
	 * @return array response
	 **/
	private function jsonDecode($json) {
		return json_decode($json, true);
	}
}
?>