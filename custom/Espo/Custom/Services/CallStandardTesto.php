<?php

namespace Espo\Custom\Services;

use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class CallStandardTesto
{
    public const CONFIG_KEY = 'callStandardTesto';

    public const DEFAULT =
        'Salve, sono Carmine Alvino di ARIEL ENERGIA, mi fa sapere entro la giornata di oggi '
        . 'poi cosa ha deciso rispetto alla proposta che le ho fatto, Grazie';

    public function __construct(
        private Config $config,
        private ConfigWriter $configWriter
    ) {}

    public function get(): string
    {
        $value = $this->config->get(self::CONFIG_KEY);

        if (!is_string($value)) {
            return self::DEFAULT;
        }

        $value = trim($value);

        return $value !== '' ? $value : self::DEFAULT;
    }

    public function persistIfChanged(?string $testo): void
    {
        if ($testo === null) {
            return;
        }

        $testo = trim($testo);

        if ($testo === '') {
            return;
        }

        if ($testo === $this->get()) {
            return;
        }

        $this->configWriter->set(self::CONFIG_KEY, $testo);
        $this->configWriter->save();
    }
}
