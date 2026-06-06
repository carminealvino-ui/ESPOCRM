// Helper condiviso: prezzi listino/codice riga contratto da listino o prodotto.
define('custom:handlers/quote/catalog-prices', [], function () {

    var aliquotaFromQuote = function (quoteModel) {
        var aliquota = quoteModel.get('aliquotaIVA');

        if (aliquota != null && aliquota > 0) {
            return aliquota;
        }

        var taxRate = quoteModel.get('taxRate');

        if (taxRate != null && taxRate > 0) {
            return taxRate < 1 ? taxRate * 100 : taxRate;
        }

        return 10;
    };

    var buildRowFromProduct = function (quoteModel, product) {
        var taxInclusive = !!quoteModel.get('isTaxInclusive');
        var aliquota = aliquotaFromQuote(quoteModel);
        var listPrice = null;
        var prezzoCodice = null;

        var listIvi = product.prezzoListinoIvaInclusa;
        var listNet = product.listPrice;
        var codiceIvi = product.prezzoCodiceIvaInclusa;
        var codiceNet = product.prezzoCodice;

        if (taxInclusive) {
            if (listIvi != null && listIvi > 0) {
                listPrice = listIvi;
            } else if (listNet != null && listNet > 0) {
                listPrice = Math.round(listNet * (1 + aliquota / 100) * 100) / 100;
            }

            if (codiceIvi != null && codiceIvi > 0) {
                prezzoCodice = codiceIvi;
            } else if (codiceNet != null && codiceNet > 0) {
                prezzoCodice = codiceNet;
            }
        } else {
            listPrice = listNet;
            prezzoCodice = codiceNet;
        }

        return {
            productId: product.id,
            listPrice: listPrice,
            prezzoCodice: prezzoCodice,
        };
    };

    var fetchRows = async function (quoteModel, productIds) {
        if (!productIds || !productIds.length) {
            return {};
        }

        var map = {};

        try {
            var response = await Espo.Ajax.postRequest('Quote/getItemCatalogPrices', {
                priceBookId: quoteModel.get('priceBookId'),
                productIds: productIds,
                isTaxInclusive: quoteModel.get('isTaxInclusive'),
                taxId: quoteModel.get('taxId'),
                dateQuoted: quoteModel.get('dateQuoted'),
                aliquotaIVA: quoteModel.get('aliquotaIVA'),
            });

            (response || []).forEach(function (row) {
                if (row.productId) {
                    map[row.productId] = row;
                }
            });
        } catch (error) {
            console.warn('Quote/getItemCatalogPrices failed', error);
        }

        var missing = productIds.filter(function (id) {
            var row = map[id];

            return !row
                || ((row.listPrice == null || row.listPrice <= 0)
                    && (row.prezzoCodice == null || row.prezzoCodice <= 0));
        });

        if (!missing.length) {
            return map;
        }

        await Promise.all(missing.map(async function (productId) {
            try {
                var product = await Espo.Ajax.getRequest('Product/' + productId, {
                    select: 'listPrice,prezzoListinoIvaInclusa,prezzoCodice,prezzoCodiceIvaInclusa',
                });
                var row = buildRowFromProduct(quoteModel, product);

                if ((row.listPrice != null && row.listPrice > 0)
                    || (row.prezzoCodice != null && row.prezzoCodice > 0)) {
                    map[productId] = row;
                }
            } catch (error) {
                console.warn('Product fallback failed for ' + productId, error);
            }
        }));

        return map;
    };

    var patchFromRow = function (item, row, currency, quoteModel) {
        var patch = {};

        if (row.listPrice != null && row.listPrice > 0) {
            patch.listPrice = row.listPrice;
            patch.listPriceCurrency = item.listPriceCurrency || currency;
        }

        if (row.prezzoCodice != null && row.prezzoCodice > 0) {
            patch.prezzoCodice = row.prezzoCodice;
            patch.prezzoCodiceCurrency = item.prezzoCodiceCurrency || currency;
        }

        if (quoteModel && quoteModel.get('isTaxInclusive')) {
            var unitPrice = item.unitPrice;

            if ((unitPrice == null || unitPrice <= 0) && row.listPrice != null && row.listPrice > 0) {
                patch.unitPrice = row.listPrice;
                patch.unitPriceCurrency = item.unitPriceCurrency || currency;
            }
        }

        return patch;
    };

    return {
        fetchRows: fetchRows,
        buildRowFromProduct: buildRowFromProduct,
        patchFromRow: patchFromRow,
    };
});
