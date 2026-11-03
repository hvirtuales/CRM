<?php

return [
    'footer' => 'Transactional message sent by HV CRM. Please do not reply to this email.',
    'dunning' => [
        'greeting'   => 'Hello {0} team,',
        'howToPay'   => 'If you already paid, please ignore this message. To pay or change plans, contact your provider.',
        'ctaCompany' => 'View my plan',
        'ctaTenant'  => 'View my portfolio subscriptions',
        'note'       => 'You receive this notice because you manage this business on HV CRM.',
        'stage'      => ['renewal' => 'upcoming renewal', 'past_due' => 'past due', 'final' => 'suspension imminent', 'suspended' => 'suspended'],
        'renewal'    => ['subject' => 'Your {0} plan renews soon', 'body' => 'The {1} plan of {0} renews on {3} for {2}.'],
        'past_due'   => ['subject' => 'Payment pending for {0}', 'body' => 'The {1} plan period of {0} ended on {3} and a payment of {2} is pending. Service remains active until {4}.'],
        'final'      => ['subject' => 'Final notice: {0} will be suspended', 'body' => 'The payment of {2} for the {1} plan of {0} is still pending. On {4} the service will become read-only.'],
        'suspended'  => ['subject' => '{0}: service suspended', 'body' => 'The {0} service ({1} plan) is read-only due to the unpaid {2}. Your data is kept; it reactivates once the payment is settled.'],
        'tenant'     => [
            'subject'  => 'Portfolio: {0} — {1}',
            'greeting' => 'Hello,',
            'action'   => 'We recommend contacting the client to collect the payment.',
        ],
    ],
];
