# linktap
a PHP class for linktaps cloud API

Fill in your API key, and app username

the taps array is used to give your taps a nice human readable name, full this in for each taplinker you have
    protected $key = '';
    protected $taps = array(
        'front_tap' => '<DEVICE ID>',
        'back_tap' => '<DEVICE ID>',
    );
    protected $gateway = '<GATEWAY ID>';
    protected $username = '<USERNAME>';
