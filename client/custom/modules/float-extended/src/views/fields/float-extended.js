define('custom:views/fields/float-extended', ['views/fields/float'], (FloatFieldView) => {
    return class extends FloatFieldView {

        // Field type name, must exactly match the type in entityDefs
        type = 'floatExtended';

        setup () {
            super.setup();
            // Custom setup logic can be added here if needed
        }

        afterRender () {
            super.afterRender();

            // For debugging: output params to F12 console
            //console.log('params:', this.params);

            // Safety check: model and field name must exist
            if (!this.model || !this.name || typeof this.model.get !== 'function') return;

            // Read value from model and convert to float
            const value = parseFloat(this.model.get(this.name));
            if (isNaN(value)) return;

            // Array of allowed render modes from params
            const renderModes = Array.isArray(this.params.RenderOn) ? this.params.RenderOn : [];

            // Check if current render mode is allowed (e.g. list, detail)
            if (!this.mode || renderModes.length === 0 || !renderModes.includes(this.mode)) return;

            // Decimal places from params, fallback to 0
            const decimalPlaces = (typeof this.params.decimalPlaces === 'number' && this.params.decimalPlaces >= 0) ? this.params.decimalPlaces : 0;

            // Unit from params, empty if not set
            const unit = (typeof this.params.unit === 'string' && this.params.unit.length > 0) ? this.params.unit : '';

            // Format text: value with decimals + optional unit
            const displayText = value.toFixed(decimalPlaces) + (unit ? ' ' + unit : '');
            const $span = $('<span>').text(displayText);

            // Optional: set bold font if isBold is true
            if (this.params.isBold === true) {
                $span.addClass('text-bold');
            }

            // Optional: set italic font if isItalic is true
            if (this.params.isItalic === true) {
                $span.addClass('text-italic');
            }

            // Determine and set label color class based on threshold values
            if (this.params.useColorLabel === true) {
                const labelClass = this.getThresholdClass(
                    value,
                    this.params.labelThresholdLowValue,
                    this.params.labelThresholdLowClass,
                    this.params.labelThresholdHighValue,
                    this.params.labelThresholdHighClass,
                    this.params.labelThresholdDefaultClass
                );
                if (labelClass) $span.addClass(labelClass);
            }

            // Determine and set text color class based on threshold values
            if (this.params.useColorText === true) {
                const textClass = this.getThresholdClass(
                    value,
                    this.params.textThresholdLowValue,
                    this.params.textThresholdLowClass,
                    this.params.textThresholdHighValue,
                    this.params.textThresholdHighClass,
                    this.params.textThresholdDefaultClass
                );
                if (textClass) $span.addClass(textClass);
            }

            // Insert span with formatted value into DOM
            this.$el.empty().append($span);
        }

        /**
         * Returns the appropriate Bootstrap color class based on thresholds
         * @param {number} value - current value
         * @param {number} lowValue - threshold for low range
         * @param {string} lowClass - CSS class for low range
         * @param {number} highValue - threshold for high range
         * @param {string} highClass - CSS class for high range
         * @param {string} defaultClass - CSS class for default range
         * @returns {string|null} CSS class or null
         */
        getThresholdClass(value, lowValue, lowClass, highValue, highClass, defaultClass) {
            if (typeof value !== 'number') return null;

            if (typeof lowValue === 'number' && value < lowValue) {
                return typeof lowClass === 'string' ? lowClass : null;
            }

            if (typeof highValue === 'number' && value > highValue) {
                return typeof highClass === 'string' ? highClass : null;
            }

            return typeof defaultClass === 'string' ? defaultClass : null;
        }

    };
});