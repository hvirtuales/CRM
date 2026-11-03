<?php

namespace App\Controllers;

use App\Libraries\NotificationService;
use App\Libraries\SignupService;
use Config\Commercial;
use Config\Services;

/** Autorregistro público «Pruébelo gratis» (ver SignupService). */
class Signup extends BaseController
{
    public function index()
    {
        if (! config(Commercial::class)->selfSignup) {
            return $this->response->setStatusCode(404)->setBody(view('auth/signup_closed'));
        }

        return view('auth/signup', ['plans' => (new SignupService())->plans(), 'termsUrl' => config(Commercial::class)->termsUrl]);
    }

    public function store()
    {
        if (! config(Commercial::class)->selfSignup) {
            return $this->response->setStatusCode(404)->setBody(view('auth/signup_closed'));
        }
        if ((string) $this->request->getPost('website_url') !== '') {
            return view('auth/signup_sent', ['devLink' => null]); // honeypot: misma respuesta, sin efectos
        }
        if (! Services::throttler()->check('signup-' . md5($this->request->getIPAddress()), 3, HOUR)) {
            return redirect()->back()->withInput()->with('error', lang('Core.tooManyRequests'));
        }
        $svc = new SignupService();
        $r   = $svc->start($this->request->getPost(), $this->request->getIPAddress());
        if (! $r->ok) {
            return redirect()->back()->withInput()->with('error', $r->message)->with('errors', $r->errors);
        }
        $devLink = ENVIRONMENT !== 'production' && ! NotificationService::emailConfigured() ? $svc->lastLinkForDevelopment : null;

        return view('auth/signup_sent', ['devLink' => $devLink]);
    }

    public function verify(string $token)
    {
        $r = (new SignupService())->verify($token);
        if (! $r->ok) {
            return $this->response->setStatusCode($r->status)->setBody(view('auth/signup_closed', ['message' => $r->message]));
        }

        // El correo quedó verificado: se continúa a definir la contraseña del propietario.
        return redirect()->to(site_url('invitacion/' . $r->data['owner_token']));
    }
}
