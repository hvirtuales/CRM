<?php

namespace Components\Formularios\Controllers;

use App\Controllers\BaseController;

/**
 * Página pública autosuficiente del formulario (estilos y SVG en línea, sin dependencias del núcleo
 * en la vista). Permite inserción (iframe) solo en los dominios declarados por el negocio.
 */
class PublicForm extends BaseController
{
    public function show(string $publicId)
    {
        $form = $this->lookup($publicId);
        if (! $form) {
            return $this->response->setStatusCode(404)->setBody(view('Components\Formularios\Views\public\unavailable'));
        }
        $this->allowEmbedding($form);

        return $this->render($form, [], null);
    }

    public function submit(string $publicId)
    {
        $form = $this->lookup($publicId);
        if (! $form) {
            return $this->response->setStatusCode(404)->setBody(view('Components\Formularios\Views\public\unavailable'));
        }
        $this->allowEmbedding($form);

        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'ref'] as $k) {
            $v = $this->request->getGet($k);
            if (is_string($v) && $v !== '') {
                $utm[$k] = mb_substr(preg_replace('/[^\w\-.~ ]/u', '', $v) ?? '', 0, 100);
            }
        }

        $r = service('formSubmissions')->submit($form, (array) $this->request->getPost(), $this->request->getIPAddress(), $utm);
        if ($r->ok) {
            return $this->render($form, [], $r->message, true);
        }

        return $this->render($form, $r->errors, $r->message, false, $r->status);
    }

    private function render(array $form, array $errors, ?string $message, bool $done = false, int $status = 200)
    {
        $brand = null;
        if ($form['tenant_id']) {
            $brand = db_connect()->table('tenants')->select('brand_color')->where('id', $form['tenant_id'])->get()->getRow()->brand_color ?? null;
        }

        return $this->response->setStatusCode($status)->setBody(view('Components\Formularios\Views\public\form', [
            'form'    => $form,
            'fields'  => json_decode((string) $form['published_fields'], true) ?: [],
            'token'   => service('formSubmissions')->issueToken($form['public_id']),
            'errors'  => $errors,
            'message' => $message,
            'done'    => $done,
            'old'     => $done ? [] : (array) ($this->request->getPost('f') ?? []),
            'brand'   => $brand,
        ]));
    }

    /**
     * Con RLS (PostgreSQL) la búsqueda por identificador opaco omite la política un instante;
     * luego se fija el negocio dueño como segunda barrera para todo lo que se escriba.
     */
    private function lookup(string $publicId): ?array
    {
        $rls = class_exists(\App\Libraries\Rls::class);
        $rls && \App\Libraries\Rls::setBypass(true);

        try {
            $form = service('formForms')->findPublished($publicId);
        } finally {
            $rls && \App\Libraries\Rls::setBypass(false);
        }
        if ($form && $rls) {
            \App\Libraries\Rls::setCompany((int) $form['company_id']);
        }

        return $form;
    }

    private function allowEmbedding(array $form): void
    {
        $domains = json_decode((string) ($form['embed_domains'] ?? ''), true) ?: [];
        if ($domains) {
            // El filtro de cabeceras del núcleo convierte esto en frame-ancestors.
            $this->response->setHeader('X-HV-Frame-Ancestors', "'self' " . implode(' ', $domains));
        }
        $this->response->setHeader('X-Robots-Tag', 'noindex');
    }
}
