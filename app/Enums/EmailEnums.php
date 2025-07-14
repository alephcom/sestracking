<?php
namespace App\Enums;

enum EmailEnums: string
{
    const EMAIL_STATUS_SENT = 'sent';
    const EMAIL_STATUS_DELIVERED = 'delivered';
    const EMAIL_STATUS_NOT_DELIVERED = 'not_delivered';

}