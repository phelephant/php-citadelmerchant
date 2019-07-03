`citadelmerchant.php` contains a stand-alone class to interact with the
CITADEL Merchant service [API][api].

Depends on *either* `curl` php extension, *either* http stream wrappers being enabled.

Note, that you need a [CITADEL Merchant][cm] account, to generate APP_KEY
and APP_SECRET.

[cm]: https://citadel.li/merchant
[api]: https://citadel.li/apidocs#merchant

## Usage

```php
//create instance of RPC
$rpc = new CitadelMerchantRPC(APP_KEY, APP_SECRET);
//transfer 0.25 BTS from alice to bob
$rpc->transfer('alice', 'bob', '0.25', 'BTS');
//sell 0.25 BTS for 0.1 STEEM
$order_id = $rpc->create_order('alice', '0.25', 'BTS', '0.1', 'STEEM');
//cancel order
$rpc->cancel_order($order_id);
//get currently active orders
$orders = $rpc->get_orders('alice');
//get history of filled orders
$orders = $rpc->get_filled_orders('alice');
```

On failure, a `CitadelMerchant_Exception` will be thrown, so you might
want to wrap all your calls like so:

```php
try {
	$rpc->do_something();
} catch (CitadelMerchant_Exception $e) {
	//log error
	//$e->getMessage();
} catch (Exception $e) {
	//unknown/generic exception
}
```

Note, that this class might not contain all the [API](api) methods, but I try
my best to keep it up to date. Pull requests welcome!

## License

This code is placed into Public Domain (CC0).

## See Also

CITADEL Merchant WooCommerce Plugin
CITADEL Merchant OpenCart Plugin
