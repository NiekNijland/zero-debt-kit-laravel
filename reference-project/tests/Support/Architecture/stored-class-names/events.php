<?php

declare(strict_types=1);

return [
    'App\\Modules\\Invoices\\Events\\InvoiceCreatedEvent',
    'App\\Modules\\Invoices\\Events\\InvoiceSentEvent',
    'App\\Modules\\Orders\\Events\\OrderCanceledEvent',
    'App\\Modules\\Orders\\Events\\OrderPaidEvent',
    'App\\Modules\\Orders\\Events\\OrderPlacedEvent',
    'App\\Modules\\Orders\\Events\\OrderShippedEvent',
    'App\\Modules\\Products\\Events\\ProductCreatedEvent',
    'App\\Modules\\Products\\Events\\ProductDeletedEvent',
    'App\\Modules\\Shipments\\Events\\ShipmentDispatchedEvent',
    'App\\Modules\\Stock\\Events\\StockLevelChangedEvent',
    'App\\Modules\\Users\\Events\\UserInvitedEvent',
    'App\\Modules\\Users\\Events\\UserSignedInEvent',
    'App\\Modules\\Users\\Events\\UserSuspendedEvent',
];
