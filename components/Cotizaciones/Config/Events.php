<?php

use CodeIgniter\Events\Events;

/** Tarea diaria del núcleo (php spark hv:lifecycle): vencimientos y recordatorios de cotizaciones. */
Events::on('hv.daily', static function (object $report): void {
    if (! service('components')->isEnabled('quote')) {
        return;
    }
    $report->results['quote'] = service('quoteQuotes')->runDaily($report->now);
});
