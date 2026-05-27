// ========================================
// VERSIONE: 1.0.0
// DATA: 2026-05-27
// FILE: client/custom/src/views/opportunity/record/edit.js
// ========================================

/* global define */

define('custom:views/opportunity/record/edit', ['crm:views/opportunity/record/edit'], function (Dep) {

    var IT_MONTHS = [
        '',
        'Gennaio',
        'Febbraio',
        'Marzo',
        'Aprile',
        'Maggio',
        'Giugno',
        'Luglio',
        'Agosto',
        'Settembre',
        'Ottobre',
        'Novembre',
        'Dicembre'
    ];

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.debouncedResolvePriceBook = Espo.Utils.debounce(
                this.resolvePriceBook.bind(this),
                300
            );

            this.listenTo(this.model, 'change:dataOpportunit', function () {
                this.debouncedResolvePriceBook();
            }, this);

            this.listenTo(this.model, 'change:productBrandId', function () {
                this.debouncedResolvePriceBook();
            }, this);

            this.listenTo(this.model, 'change:productBrandName', function () {
                this.debouncedResolvePriceBook();
            }, this);

            this.listenTo(this.model, 'change:azienda', function () {
                this.debouncedResolvePriceBook();
            }, this);
        },

        resolveReferenceDate: function () {
            var date = this.model.get('dataOpportunit') || this.model.get('closeDate');

            if (date) {
                return String(date).substring(0, 10);
            }

            return Espo.Utils.getDateToday();
        },

        resolveBrandKey: function () {
            var name = this.model.get('productBrandName') || this.model.get('azienda') || '';

            return String(name).trim().toUpperCase();
        },

        nameMatchesReferenceMonth: function (name, refDate) {
            if (!name || !refDate) {
                return false;
            }

            var m = Espo.Utils.getDateMoment(refDate);

            if (!m || !m.isValid()) {
                return false;
            }

            var label = IT_MONTHS[m.month() + 1] + ' ' + m.year();

            return String(name).toLowerCase().indexOf(label.toLowerCase()) !== -1;
        },

        isEffectiveOnDate: function (priceBook, refDate) {
            var start = priceBook.dateStart ? String(priceBook.dateStart).substring(0, 10) : null;
            var end = priceBook.dateEnd ? String(priceBook.dateEnd).substring(0, 10) : null;

            if (start && start > refDate) {
                return false;
            }

            if (end && end < refDate) {
                return false;
            }

            if (start || end) {
                return true;
            }

            return this.nameMatchesReferenceMonth(priceBook.name, refDate);
        },

        scoreCandidate: function (priceBook, refDate, brandKey) {
            var score = 0;
            var name = String(priceBook.name || '').toUpperCase();

            if (name.indexOf(brandKey) === 0) {
                score += 10;
            }

            if (this.nameMatchesReferenceMonth(priceBook.name, refDate)) {
                score += 100;
            }

            if (priceBook.dateStart) {
                score += parseInt(String(priceBook.dateStart).replace(/-/g, '').substring(0, 8), 10) || 0;
            }

            return score;
        },

        resolvePriceBook: function () {
            if (!this.model.has('priceBookId')) {
                return;
            }

            if (this.model.get('priceBookId') && this.model.get('_priceBookManual')) {
                return;
            }

            var brandKey = this.resolveBrandKey();
            var refDate = this.resolveReferenceDate();

            if (!brandKey || !refDate) {
                return;
            }

            var where = [
                {
                    type: 'startsWith',
                    attribute: 'name',
                    value: brandKey
                }
            ];

            if (this.model.get('productBrandId')) {
                where = [
                    {
                        type: 'equals',
                        attribute: 'productBrandId',
                        value: this.model.get('productBrandId')
                    }
                ];
            }

            Espo.Ajax.getRequest('PriceBook', {
                where: where,
                select: ['id', 'name', 'dateStart', 'dateEnd', 'productBrandId'],
                maxSize: 200
            }).then(function (response) {
                if (!response.list || !response.list.length) {
                    return;
                }

                var best = null;
                var bestScore = -1;

                response.list.forEach(function (item) {
                    if (!this.isEffectiveOnDate(item, refDate)) {
                        return;
                    }

                    var score = this.scoreCandidate(item, refDate, brandKey);

                    if (score > bestScore) {
                        bestScore = score;
                        best = item;
                    }
                }.bind(this));

                if (!best) {
                    return;
                }

                this.model.set({
                    priceBookId: best.id,
                    priceBookName: best.name
                });
            }.bind(this));
        }
    });
});
