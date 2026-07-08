<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Controllers\Record;

/**
 * Controller base Opportunity.
 *
 * Necessario quando scope/metadata lato server risolve il controller
 * nel namespace Custom. Le action custom restano gestite da app/actions.
 */
class Opportunity extends Record
{
}
