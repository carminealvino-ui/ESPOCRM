<?php

// =========================================
// VERSIONE: 1.0.3
// DATA: 2026-05-11 06:20
// AUTORE: CARMINE ALVINO + CHATGPT
// FILE:
// custom/Espo/Custom/Hooks/Opportunity/AutoCreateQuote.php
// BASE: 1.0.2
// =========================================
//
// FIX 1.0.3
// -----------------------------------------------------
// Correzione compatibilità hook EspoCRM 9.3.4
//
// PROBLEMA
// -----------------------------------------------------
// Espo\Core\Hooks\Base genera errore:
//
// get_class_methods():
// Argument #1 must be object or valid class
//
// SOLUZIONE
// -----------------------------------------------------
// Hook convertito in classe semplice.
//
// OBIETTIVO
// -----------------------------------------------------
// Hook lasciato volutamente neutro.
//
// La creazione Contratto NON deve più essere
// automatica.
//
// =========================================

namespace Espo\Custom\Hooks\Opportunity;

use Espo\ORM\Entity;

class AutoCreateQuote
{
    // =========================================
    // AFTER SAVE
    // =========================================

    public function afterSave(Entity $entity, array $options = [])
    {
        // =========================================
        // HOOK DISABILITATO VOLUTAMENTE
        // =========================================

        return;
    }
}

