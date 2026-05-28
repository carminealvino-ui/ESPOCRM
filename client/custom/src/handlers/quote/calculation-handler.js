define('custom:handlers/quote/calculation-handler', ['sales:handlers/quote-calculation-handler'], function (Dep) {

    return class extends Dep {

        calculate(model) {
            Dep.prototype.calculate.call(this, model);
            this.applyImportoContrattoPricing(model);
        }

        calculateItem(itemModel, field) {
            Dep.prototype.calculateItem.call(this, itemModel, field);

            const quoteModel = this.resolveQuoteModel(itemModel);

            if (quoteModel) {
                this.applyImportoContrattoPricing(quoteModel);
            }
        }

        resolveQuoteModel(itemModel) {
            if (itemModel.collection && itemModel.collection.parentModel) {
                return itemModel.collection.parentModel;
            }

            if (typeof itemModel.getParentModel === 'function') {
                return itemModel.getParentModel();
            }

            return null;
        }

        shouldApplyImportoContratto(model) {
            const importo = parseFloat(model.get('importoContratto'));

            if (!importo || importo <= 0) {
                return false;
            }

            return !!model.get('isTaxInclusive');
        }

        getAliquotaPercent(model) {
            const aliquota = parseFloat(model.get('aliquotaIVA'));

            if (aliquota > 0) {
                return aliquota;
            }

            const taxRate = parseFloat(model.get('taxRate'));

            if (taxRate > 0) {
                return taxRate < 1 ? taxRate * 100 : taxRate;
            }

            return 10;
        }

        applyImportoContrattoPricing(model) {
            if (!this.shouldApplyImportoContratto(model)) {
                return;
            }

            const importoGross = parseFloat(model.get('importoContratto'));
            const itemList = Espo.Utils.cloneDeep(model.get('itemList') || []);

            if (!itemList.length) {
                return;
            }

            const aliquota = this.getAliquotaPercent(model);
            const taxFactor = 1 + aliquota / 100;
            const weights = [];
            let totalWeight = 0;

            itemList.forEach(function (item) {
                const list = parseFloat(item.listPrice) || 0;
                const qty = parseFloat(item.quantity) || 1;
                const w = list > 0 ? list * qty : 0;

                weights.push(w);
                totalWeight += w;
            });

            let allocated = 0;

            itemList.forEach(function (item, index) {
                const qty = parseFloat(item.quantity) || 1;
                let lineGross;

                if (totalWeight > 0) {
                    if (index === itemList.length - 1) {
                        lineGross = Math.round((importoGross - allocated) * 100) / 100;
                    } else {
                        lineGross = Math.round(importoGross * (weights[index] / totalWeight) * 100) / 100;
                        allocated += lineGross;
                    }
                } else {
                    lineGross = Math.round((importoGross / itemList.length) * 100) / 100;
                }

                const unitGross = Math.round((lineGross / qty) * 100) / 100;
                const lineNet = Math.round((lineGross / taxFactor) * 100) / 100;
                const lineTax = Math.round((lineGross - lineNet) * 100) / 100;

                item.unitPrice = unitGross;
                item.amount = lineNet;
                item.taxAmount = lineTax;
            });

            const net = Math.round((importoGross / taxFactor) * 100) / 100;
            const tax = Math.round((importoGross - net) * 100) / 100;

            model.set({
                itemList: itemList,
                amount: net,
                taxAmount: tax,
                grandTotalAmount: importoGross,
            });
        }
    };
});
