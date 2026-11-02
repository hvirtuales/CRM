<?php

namespace App\Controllers;

use App\Libraries\Result;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Psr\Log\LoggerInterface;

/**
 * Controlador base del núcleo (también lo extienden los componentes).
 * Los controladores son delgados: validan forma, delegan en servicios y traducen Result a HTTP.
 */
abstract class BaseController extends Controller
{
    protected $helpers = ['url', 'form', 'hv'];

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
    }

    /** Redirige según el resultado: éxito → flash ok; fallo → errores + entrada previa. */
    protected function afterResult(Result $result, string $successTo, ?string $successMessage = null, ?string $failTo = null): RedirectResponse
    {
        if ($result->ok) {
            return redirect()->to(site_url($successTo))->with('message', $successMessage ?? $result->message ?? lang('Core.saved'));
        }

        $redirect = $failTo !== null ? redirect()->to(site_url($failTo)) : redirect()->back();

        return $redirect->withInput()->with('error', $result->message)->with('errors', $result->errors);
    }

    /** Entero positivo desde POST o null. */
    protected function postInt(string $key): ?int
    {
        $v = $this->request->getPost($key);

        return is_numeric($v) && (int) $v > 0 ? (int) $v : null;
    }

    protected function actorId(): int
    {
        return (int) Services::context()->userId();
    }

    /**
     * Paginación simple sobre un Query Builder (offset), tamaño de página 50 como el sistema de diseño.
     *
     * @return array{rows: list<array<string,mixed>>, page:int, perPage:int, total:int}
     */
    protected function paginate(\CodeIgniter\Database\BaseBuilder $builder, int $perPage = 50): array
    {
        $page  = max(1, (int) ($this->request->getGet('page') ?? 1));
        $total = (clone $builder)->countAllResults(false);
        $rows  = $builder->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();

        return ['rows' => $rows, 'page' => $page, 'perPage' => $perPage, 'total' => $total];
    }
}
