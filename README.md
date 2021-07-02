# linktap
a PHP class for linktaps cloud API

Fill in your API key, and app username

the taps array is used to give your taps a nice human readable name, full this in for each taplinker you have
<br>    protected $key = '';
<br>    protected $taps = array(
<br>        'front_tap' => '<DEVICE ID>',
<br>        'back_tap' => '<DEVICE ID>',
<br>    );
<br>    protected $gateway = '<GATEWAY ID>';
<br>    protected $username = '<USERNAME>';

  The api has a 5 minute rate limit between each request. This is handled by caching the response to a a file called cache.json.
