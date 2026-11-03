<?php

return [
    'footer' => 'Mensaje transaccional enviado por HV CRM. No responda a este correo.',
    'dunning' => [
        'greeting'   => 'Hola, equipo de {0}:',
        'howToPay'   => 'Si ya realizó el pago, ignore este mensaje. Para pagar o cambiar de plan, contacte a su proveedor.',
        'ctaCompany' => 'Ver mi plan',
        'ctaTenant'  => 'Ver suscripciones de mi cartera',
        'note'       => 'Recibe este aviso porque administra este negocio en HV CRM.',
        'stage'      => ['renewal' => 'renovación próxima', 'past_due' => 'en mora', 'final' => 'suspensión inminente', 'suspended' => 'suspendido'],
        'renewal'    => ['subject' => 'Su plan de {0} se renueva pronto', 'body' => 'El plan {1} de {0} se renueva el {3} por {2}.'],
        'past_due'   => ['subject' => 'Pago pendiente de {0}', 'body' => 'El periodo del plan {1} de {0} venció el {3} y está pendiente el pago de {2}. El servicio sigue activo hasta el {4}.'],
        'final'      => ['subject' => 'Último aviso: {0} será suspendido', 'body' => 'El pago de {2} del plan {1} de {0} sigue pendiente. El {4} el servicio pasará a solo lectura.'],
        'suspended'  => ['subject' => '{0}: servicio suspendido', 'body' => 'El servicio de {0} (plan {1}) está en solo lectura por falta de pago de {2}. Sus datos se conservan; al regularizar el pago se reactiva.'],
        'tenant'     => [
            'subject'  => 'Cartera: {0} — {1}',
            'greeting' => 'Hola:',
            'action'   => 'Le recomendamos contactar al cliente para gestionar el pago.',
        ],
    ],
];
