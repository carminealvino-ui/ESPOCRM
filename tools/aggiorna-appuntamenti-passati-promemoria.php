<?php

/**
 * DEPRECATO: non crea più Call.
 * Usa: php tools/sync-promemoria-esistenti.php [--apply]
 */

fwrite(STDERR, "Questo script non crea più Call.\n");
fwrite(STDERR, "Usa invece:\n");
fwrite(STDERR, "  php tools/sync-promemoria-esistenti.php\n");
fwrite(STDERR, "  php tools/sync-promemoria-esistenti.php --apply\n");
exit(1);
