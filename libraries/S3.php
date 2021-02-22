<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class S3
{
	protected $ci;

	protected $aws_access_key_id;
	protected $aws_secret_access_key;
	protected $bucket_name;
	protected $aws_region;
	protected $host_name;
	protected $aws_service_name;
	protected $timestamp;
	protected $date;

	protected $content_type;
	protected $content_acl;
	protected $content;
	protected $content_title;
	
	protected $canonical_headers;
	protected $signed_headers;
	function __construct()
	{
		$this->ci =& get_instance();
     	$this->ci->load->config('s3_config');

     	$this->aws_access_key_id=$this->ci->config->item('aws_access_key_id');
		$this->aws_secret_access_key=$this->ci->config->item('aws_secret_access_key');
		$this->bucket_name=$this->ci->config->item('bucket_name');
		$this->aws_region=$this->ci->config->item('aws_region');

		$this->host_name=$this->bucket_name . '.s3.amazonaws.com';
		$this->aws_service_name='s3';
		$this->timestamp=gmdate('Ymd\THis\Z');
		$this->date=gmdate('Ymd');
	}
	public function put_object($params){
		$this->assign_params($params);

		// HTTP request headers as key & value
		$request_headers = array();
		$request_headers['Content-Type'] = $this->content_type;
		$request_headers['Date'] = $this->timestamp;
		$request_headers['Host'] = $this->host_name;
		$request_headers['x-amz-acl'] = $this->content_acl;
		$request_headers['x-amz-content-sha256'] = hash('sha256', $this->content);
		ksort($request_headers);
		$canonical_headers = [];
		foreach($request_headers as $key => $value) {
			$canonical_headers[] = strtolower($key) . ":" . $value;
		}
		$canonical_headers = implode("\n", $canonical_headers);
		$canonical_headers;

		// Signed headers
		$signed_headers = [];
		foreach($request_headers as $key => $value) {
			$signed_headers[] = strtolower($key);
		}
		$signed_headers = implode(";", $signed_headers);
		$signed_headers;

		// Cannonical request 
		$canonical_request = [];
		$canonical_request[] = "PUT";
		$canonical_request[] = "/" . $this->content_title;
		$canonical_request[] = "";
		$canonical_request[] = $canonical_headers;
		$canonical_request[] = "";
		$canonical_request[] = $signed_headers;
		$canonical_request[] = hash('sha256', $this->content);
		$canonical_request = implode("\n", $canonical_request);
		$hashed_canonical_request = hash('sha256', $canonical_request);

		// AWS Scope
		$scope = [];
		$scope[] = $this->date;
		$scope[] = $this->aws_region;
		$scope[] = $this->aws_service_name;
		$scope[] = "aws4_request";

		// String to sign
		$string_to_sign = [];
		$string_to_sign[] = "AWS4-HMAC-SHA256"; 
		$string_to_sign[] = $this->timestamp; 
		$string_to_sign[] = implode('/', $scope);
		$string_to_sign[] = $hashed_canonical_request;
		$string_to_sign = implode("\n", $string_to_sign);

		// Signing key
		$kSecret = 'AWS4' . $this->aws_secret_access_key;
		$kDate = hash_hmac('sha256', $this->date, $kSecret, true);
		$kRegion = hash_hmac('sha256', $this->aws_region, $kDate, true);
		$kService = hash_hmac('sha256', $this->aws_service_name, $kRegion, true);
		$kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

		// Signature
		$signature = hash_hmac('sha256', $string_to_sign, $kSigning);

		// Authorization
		$authorization = [
			'Credential=' . $this->aws_access_key_id . '/' . implode('/', $scope),
			'SignedHeaders=' . $signed_headers,
			'Signature=' . $signature
		];
		$authorization = 'AWS4-HMAC-SHA256' . ' ' . implode( ',', $authorization);
		// Curl headers
		$curl_headers = [ 'Authorization: ' . $authorization ];
		foreach($request_headers as $key => $value) {
			$curl_headers[] = $key . ": " . $value;
		}

		$url = 'https://' . $this->host_name . '/' . $this->content_title;
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $curl_headers);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $this->content);
		$result=curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		print_r($result);
		echo "<br>";
		if($http_code != 200){
			return false;
		} 
		else{
			return true;
		}

	}
	private function assign_params($params){
		foreach ($params as $key => $value) {
			$this->$key=$value;
		}
		return true;
	} 
}