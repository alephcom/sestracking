<?php
namespace App\Enums;

enum EmailEventEnums: string
{
    const EVENT_SEND = 'send';
    const EVENT_DELIVERY = 'delivery';
    const EVENT_REJECT = 'reject';
    const EVENT_BOUNCE = 'bounce';
    const EVENT_COMPLAINT = 'complaint';
    const EVENT_FAILURE = 'failure';
    const EVENT_OPEN = 'open';
    const EVENT_CLICK = 'click';

}