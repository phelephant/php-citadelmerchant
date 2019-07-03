<?php
/*
 * CITADEL Merchant RPC, LICENSE: Public Domain/CC0
 *
 */
class CitadelMerchantException extends Exception { }

class CitadelMerchantRPC {
	public function __construct($api_secret, $api_public)
	{
		$this->api_secret = $api_secret;
		$this->api_public = $api_public;
		$this->base_url = 'https://citadel.li/merchant_api/v1/';
		$this->raw = !function_exists('curl_init');
	}

	private function _http_curl($url, $method, $data, $flags)
	{
		$content = '';
		$ctype = 'application/x-www-form-urlencoded';
		$auth_header = 'X-Citadel-Auth';
		$auth_value = $this->api_secret;
		if ($flags == 'json') {
			$content = json_encode($data);
			$ctype = 'application/json';
		}
		if ($flags == 'pub') {
			$auth_header = 'X-Citadel-Public';
			$auth_value = $this->api_public;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		if ($method == 'POST' || $method == 'PUT')
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
		}
		else if ($method == 'DELETE')
		{
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			'Content-Type: ' . $ctype,
			'Content-Length: ' . strlen($content),
			$auth_header .': ' . $auth_value,
		));
		$result = curl_exec($ch);
		curl_close($ch);

		return $this->decode_result($result);
	}

	private function _http_raw($url, $method, $data, $flags)
	{
		$content = '';
		$ctype = 'application/x-www-form-urlencoded';
		$auth_header = 'X-Citadel-Auth';
		$auth_value = $this->api_secret;
		if ($flags == 'json') {
			$content = json_encode($data);
			$ctype = 'application/json';
		}
		if ($flags == 'pub') {
			$auth_header = 'X-Citadel-Public';
			$auth_value = $this->api_public;
		}

		$result = file_get_contents(
			$url,
			false,
			stream_context_create(array(
			'http' => array(
				'header' => array(
					'Content-Type: ' .$ctype,
					'Content-Length: ' . strlen($content),
					$auth_header .': ' . $auth_value,
					),
				'method' => $method,
				'content' => $content,
				)
			))
		);

		return $this->_decode_result($result);
	}

	private function _decode_result($result)
	{
		if (!$result) throw new CitadelMerchantException('Unable to fetch response.');
		try {
			$result = json_decode($result, true);
		} catch (Exception $e) {
			throw new CitadelMerchantException('Unable to decode response.');
		}
		return $result;
	}

	public function fullURL($url)
	{
		return rtrim($this->base_url, '/').'/'.ltrim($url,'/');
	}

	public function http_request($url, $method='GET', $data=array(), $flags='')
	{
		$url = $this->fullURL($url);
		if ($this->raw)
		{
			return $this->_http_raw($url, $method, $data, $flags);
		}
		else
		{
			return $this->_http_curl($url, $method, $data, $flags);
		}
	}

	private function precise($amt, $prec)
	{
		return sprintf("%0.".$prec."f", $amt);
	}

	/*** API METHODS ***/

	/** Misc public APIs **/
	public function get_ticker($lcoin, $rcoin)
	{
		return $this->http_request('/bitshares/ticker/'.$lcoin.'/'.$rcoin, 'GET', [ ], 'pub');
	}
	public function get_bitshares_balances($account)
	{
		return $this->http_request('/bitshares/balances?account='.$account);
	}
	public function get_bitshares_asset($symbol)
	{
		return $this->http_request('/bitshares/assets/'.$symbol);
	}

	/** Payment Processor **/
	public function get_invoice($invoice_id)
	{
		return $this->http_request('/invoices/'.$invoice_id, 'GET');
	}
	public function create_invoice($invoice_data)
	{
		return $this->http_request('/invoices', 'PUT', $invoice_data, 'json');
	}
	public function cashout_invoice($invoice_id, $method, $address)
	{
		return $this->http_request('/invoices', 'POST', array(
			'invoice_id' => $invoice_id,
			'method' => $method,
			'address' => $address,
		), 'json');
	}

	/** Trading & BitShares API **/
	public function create_account($name) {
		return $this->http_request('/accounts', 'PUT', array(
			'username' => $name
		), 'json');
	}
	public function get_accounts() {
		return $this->http_request('/accounts', 'GET');
	}
	public function get_account($account) {
		return $this->http_request('/accounts/'.$account, 'GET');
	}
	public function get_account_balances($account) {
		return $this->http_request('/accounts/'.$account.'/balances', 'GET');
	}

	public function transfer($from, $to, $amount, $symbol, $memo='', $fee_asset=null) {
		return $this->http_request('/accounts/'.$from.'/transfer', 'PUT', array(
			'asset' => $symbol,
			'amount' => $amount,
			'to' => $to,
			'memo' => $memo,
			'fee_asset' => $fee_asset,
		), 'json');
	}
	public function get_history($account, $offset=0, $limit=100)
	{
		return $this->http_request('/accounts/'.$account.
			'?offset='.$offset.'&limit='.$limit, 'GET'
		);
	}

	// Returns order_id
	public function create_order($account, $input_amount, $input_asset,
		$output_amount, $output_asset, $expiration=0, $fill_or_kill=false)
	{
		return $this->http_request('/accounts/'.$account.'/orders',
			'PUT', array(
				'input_amount' => $input_amount,
				'input_asset' => $input_asset,
				'output_amount' => $output_amount,
				'output_asset' => $output_asset,
				'expiration' => $expiration,
				'fill_or_kill' => $fill_or_kill,
			), 'json'
		);
	}
	public function cancel_order($account, $order_id)
	{
		return $this->http_request(
			'/accounts/'.$account.'/orders/'.$order_id,
			'DELETE'
		);
	}

	public function get_orders($account)
	{
		return $this->http_request('/accounts/'.$account.'/orders', 'GET');
	}
	public function get_filled_orders($account)
	{
		return $this->http_request('/accounts/'.$account.'/filled-orders', 'GET');
	}
	public function get_market($base, $quote)
	{
		return $this->http_request('/markets/'.$base.'/'.$quote, 'GET');
	}

}

/* This helper class can be used to "assemble" invoice data programmatically,
 * and export it to proper JSON, e.g.:
    $rpc = new CitadelMerchantRPC();
    $invoice = new CitadelMerchantInvoice();
    $invoice->setDescription("Payment #1");
    $invoice->setTotal('0.001', 'BTC');
    $rpc->create_invoice($invoice->asDATA());
 */
class CitadelMerchantInvoice {
	public function __construct() {
		$this->positions = array();
		$this->description = '';
		$this->total_amount = 0;
		$this->total_coin = '';
		$this->userdata_id = '';
		$this->return_url = '';
		$this->callback_url = '';
		$this->callback_method = 'POST';
		$this->cashout_method = null;
		$this->cashout_address = null;
	}
	public function setTotal($amount, $coin=null)
	{
		$this->total_amount = $amount;
		if ($coin) $this->total_coin = $coin;
	}
	public function setDescription($desc)
	{
		$this->description = $desc;
	}
	public function setUserData($data)
	{
		$this->userdata_id = $data;
	}
	public function setCallback($url, $method='POST')
	{
		$this->callback_url = $url;
		$this->callback_method = $method;
	}
	public function setCashout($method, $address)
	{
		$this->cashout_method = $method;
		$this->cashout_address = $address;
	}
	public function setReturnURL($url)
	{
		$this->return_url = $url;
	}
	public function addPosition($desc, $qty, $price, $total=null, $coin=null)
	{
		$pos = array(
			'description' => $desc,
			'quantity' => $qty,
			'price' => $price,
			'total' => $total,
		);
		if ($coin && $this->total_coin && $this->total_coin != $coin)
		{
			throw new Exception("Total coin already set and does not match position coin.");
		}
		if ($coin && !$this->total_coin)
		{
			$this->total_coin = $coin;
		}
	}

	public function asData() {
		$total = $this->total_amount;
		if (!$total && $this->positions) {
			foreach ($this->positions as $pos) {
				$total += $pos['total'];
			}
		}
		return array(
			"userdata_id" => $this->userdata_id,
			"callback_url" => $this->callback_url,
			"callback_method" => $this->callback_method,
			"return_url"=> $this->return_url,
			"description" => $this->description,
			"total_amount" => $total,
			"total_coin" => $this->total_coin,
			"positions" => $this->positions,
			"cashout" => !$this->cashout_method ? null : array(
				"method"=> $this->cashout_method,
				"address"=> $this->cashout_address,
			)
		);
	}
}

?>