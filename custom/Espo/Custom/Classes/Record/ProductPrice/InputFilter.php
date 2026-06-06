<?php

namespace Espo\Custom\Classes\Record\ProductPrice;

use Espo\Core\Record\Input\Data;
use Espo\Core\Record\Input\Filter;
use Espo\Custom\Services\IvaDualPriceSync;

/**
 * Popola price e coppie IVA dai campi dual-IVA prima della validazione record (Espo 8.2+).
 *
 * @noinspection PhpUnused
 */
class InputFilter implements Filter
{
    public function __construct(
        private IvaDualPriceSync $ivaDualPriceSync
    ) {}

    public function filter(Data $data): void
    {
        $this->ivaDualPriceSync->prepareProductPriceInput($data);
    }
}
