/**
 * ============================================================
 * ENTITÀ: Disponibilita
 * FILE: create.js
 * VERSIONE: 1.1.0
 * DATA: 2026-05-07
 * STATO: STABILE PRODUZIONE
 *
 * ------------------------------------------------------------
 * CONTESTO
 * ------------------------------------------------------------
 * Hook PHP = gestione ufficiale:
 *  - name
 *  - dateStart / dateEnd
 *  - isAllDay
 *
 * JS = SOLO UX:
 *  - default iniziali
 *  - gestione cambio data
 *
 * ------------------------------------------------------------
 * FIX IMPLEMENTATI
 * ------------------------------------------------------------
 * ✔ rimosso conflitto con Hook
 * ✔ blocco sovrascrittura input utente
 * ✔ gestione intelligente default
 * ============================================================
 */

define('custom:views/disponibilita/create', 'views/record/edit', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            // ============================================
            // FLAG: utente ha modificato orari
            // ============================================
            this.userModifiedTime = false;

            // ============================================
            // Se utente modifica orari → blocca automazione
            // ============================================
            this.listenTo(this.model, 'change:orarioInizio', () => {
                this.userModifiedTime = true;
            });

            this.listenTo(this.model, 'change:orarioFine', () => {
                this.userModifiedTime = true;
            });

            // ============================================
            // Cambio data → aggiorna orari (se consentito)
            // ============================================
            this.listenTo(this.model, 'change:dateStart', () => {
                this.updateFromDateStart();
            });

            this.listenTo(this.model, 'change:dateStartDate', () => {
                this.updateFromDateStart();
            });

            this.listenTo(this.model, 'change:datadisponibilita', () => {
                this.updateFromDataDisponibilita();
            });

            // ============================================
            // After render → default iniziali
            // ============================================
            this.on('after:render', () => {
                this.setDefaultValues();
            });
        },

        // ============================================
        // DEFAULT INIZIALI
        // ============================================
        setDefaultValues: function () {

            if (!this.model.isNew()) return;

            let dateStart = this.model.get('dateStart');
            let dataDisp = dateStart
                ? moment(dateStart).format('YYYY-MM-DD')
                : this.model.get('datadisponibilita');

            if (!dataDisp) {
                dataDisp = moment().format('YYYY-MM-DD');
            }

            if (!dateStart) {
                this.model.set('dateStart', dataDisp + ' 11:30:00');
            }

            this.model.set('datadisponibilita', dataDisp);

            if (!this.model.get('orarioInizio')) {
                this.model.set('orarioInizio', dataDisp + ' 11:30:00');
            }

            if (!this.model.get('orarioFine')) {
                this.model.set('orarioFine', dataDisp + ' 18:30:00');
            }
        },

        updateFromDateStart: function () {
            if (this.userModifiedTime) return;

            let dateStartDate = this.model.get('dateStartDate');
            let dateStart = this.model.get('dateStart');
            let dataDisp = dateStartDate
                ? moment(dateStartDate).format('YYYY-MM-DD')
                : (dateStart ? moment(dateStart).format('YYYY-MM-DD') : null);

            if (!dataDisp) return;

            this.model.set('datadisponibilita', dataDisp);

            if (!this.model.get('orarioInizio')) {
                this.model.set('orarioInizio', dataDisp + ' 11:30:00');
            }

            if (!this.model.get('orarioFine')) {
                this.model.set('orarioFine', dataDisp + ' 18:30:00');
            }
        },

        // ============================================
        // UPDATE SU CAMBIO DATA
        // ============================================
        updateFromDataDisponibilita: function () {

            // se utente ha modificato → NON toccare
            if (this.userModifiedTime) return;

            let dataDisp = this.model.get('datadisponibilita');
            if (!dataDisp) return;

            this.model.set('orarioInizio', dataDisp + ' 11:30:00');
            this.model.set('orarioFine', dataDisp + ' 18:30:00');
        }

    });
});
