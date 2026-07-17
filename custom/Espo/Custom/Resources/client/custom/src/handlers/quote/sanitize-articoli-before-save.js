define('custom:handlers/quote/sanitize-articoli-before-save', [], function () {

    var sanitizeItemList = async function (itemList) {
        if (!itemList || !itemList.length) {
            return itemList;
        }

        var cleaned = [];
        var removed = 0;

        for (var i = 0; i < itemList.length; i++) {
            var item = Espo.Utils.cloneDeep(itemList[i]);

            if (!item || !item.productId) {
                cleaned.push(item);

                continue;
            }

            try {
                await Espo.Ajax.getRequest('Product/' + item.productId, {select: 'id'});
                cleaned.push(item);
            } catch (error) {
                item.productId = null;
                item.productName = null;
                cleaned.push(item);
                removed++;
            }
        }

        if (removed > 0) {
            Espo.Ui.warning(
                'Rimosso il collegamento a ' + removed + ' prodotto/i non più presenti nel catalogo. ' +
                'Le righe restano con i prezzi salvati; riseleziona il prodotto se serve.'
            );
        }

        return cleaned;
    };

    return class {

        constructor(view) {
            this.view = view;
        }

        process() {
            if (this.view.scope !== 'Quote' || !this.view.model || this.view.model._sanitizeArticoliSaveHook) {
                return;
            }

            this.view.model._sanitizeArticoliSaveHook = true;

            var model = this.view.model;
            var originalSave = model.save.bind(model);

            model.save = function (data, options) {
                data = data || {};

                var itemList = data.itemList;

                if (itemList == null && model.has('itemList')) {
                    itemList = model.get('itemList');
                }

                if (!itemList || !itemList.length) {
                    return originalSave(data, options);
                }

                return sanitizeItemList(itemList).then(function (cleaned) {
                    data.itemList = cleaned;
                    model.set('itemList', cleaned, {silent: true});

                    return originalSave(data, options);
                });
            };
        }
    };
});
