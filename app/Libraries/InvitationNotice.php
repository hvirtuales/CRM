<?php

namespace App\Libraries;

use Config\Services;

/**
 * Solo FUERA de producción y sin correo configurado: muestra al usuario local el enlace de la
 * última invitación para poder probar el flujo. En producción nunca se expone.
 */
final class InvitationNotice
{
    public static function flash(): void
    {
        $link = Services::invitations()->lastLinkForDevelopment;
        if (ENVIRONMENT !== 'production' && $link !== null && (string) config('Email')->fromEmail === '') {
            session()->setFlashdata('invitation_link', $link);
        }
    }
}
