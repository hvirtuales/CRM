<?php

namespace Config;

use CodeIgniter\Config\AutoloadConfig;

/**
 * Autocarga del núcleo HV CRM.
 *
 * Único enganche de infraestructura para componentes: cada subcarpeta de `components/`
 * que contenga un `Manifest.php` se registra como namespace PSR-4 `Components\<Carpeta>`.
 * A partir de ahí CodeIgniter descubre sus rutas, servicios, eventos, filtros e idiomas.
 */
class Autoload extends AutoloadConfig
{
    /**
     * @var array<string, list<string>|string>
     */
    public $psr4 = [
        APP_NAMESPACE => APPPATH,
    ];

    /**
     * @var array<string, string>
     */
    public $classmap = [];

    /**
     * @var list<string>
     */
    public $files = [];

    /**
     * Helpers del núcleo disponibles en todo request (vistas de componentes incluidas).
     *
     * @var list<string>
     */
    public $helpers = ['url', 'form', 'hv'];

    public function __construct()
    {
        parent::__construct();

        foreach (self::componentDirectories() as $folder => $path) {
            $this->psr4['Components\\' . $folder] = $path;
        }
    }

    /**
     * Carpetas de componentes válidos: PascalCase y con Manifest.php.
     *
     * @return array<string, string> carpeta => ruta absoluta
     */
    public static function componentDirectories(): array
    {
        $base = ROOTPATH . 'components' . DIRECTORY_SEPARATOR;
        $dirs = [];

        if (! is_dir($base)) {
            return $dirs;
        }

        foreach (scandir($base) ?: [] as $entry) {
            if (! preg_match('/^[A-Z][A-Za-z0-9]{1,40}$/', $entry)) {
                continue; // evita '.', '..' y nombres que no producen un namespace válido
            }
            $path = $base . $entry;
            if (is_dir($path) && is_file($path . DIRECTORY_SEPARATOR . 'Manifest.php')) {
                $dirs[$entry] = $path . DIRECTORY_SEPARATOR;
            }
        }

        ksort($dirs);

        return $dirs;
    }
}
