<?php

return [
    'labels' => [
        'billing' => 'Billing',
        'gateways' => 'Gateways',
        'zibal' => 'Zibal',
        'status' => 'Zibal status',
        'merchant' => 'Zibal merchant',
    ],

    'descriptions' => [
        'billing' => 'Payment and invoicing settings for the bot.',
        'gateways' => 'Enable and configure the payment methods customers can use.',
        'zibal' => 'Accept payments through the Zibal payment gateway.',
        'status' => 'Turn the Zibal payment gateway on or off.',
        'merchant' => 'Your Zibal merchant code, used to authenticate payment requests.',
    ],

    'alerts' => [
        'merchant' => "Zibal refused to start a payment because of the merchant ID ([:code] :message). Members can't pay through Zibal until it is fixed in the Zibal gateway settings.",
    ],
];
