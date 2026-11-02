<?php

namespace App\Libraries;

/**
 * Colector que se pasa al evento `dashboard.render`. Los componentes añaden KPIs y tarjetas
 * (HTML ya escapado por su propia vista) sin conocer la vista del núcleo.
 */
final class DashboardWidgets
{
    /** @var list<array{label:string, value:string, hint:?string, tone:?string, order:int}> */
    private array $kpis = [];

    /** @var list<array{html:string, order:int, wide:bool}> */
    private array $cards = [];

    public function __construct(public readonly int $companyId)
    {
    }

    public function addKpi(string $label, string $value, ?string $hint = null, ?string $tone = null, int $order = 50): void
    {
        $this->kpis[] = compact('label', 'value', 'hint', 'tone', 'order');
    }

    public function addCard(string $html, int $order = 50, bool $wide = false): void
    {
        $this->cards[] = compact('html', 'order', 'wide');
    }

    /** @return list<array<string, mixed>> */
    public function kpis(): array
    {
        usort($this->kpis, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return $this->kpis;
    }

    /** @return list<array<string, mixed>> */
    public function cards(): array
    {
        usort($this->cards, static fn ($a, $b) => $a['order'] <=> $b['order']);

        return $this->cards;
    }
}
