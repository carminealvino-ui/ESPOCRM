/* global define */

define('custom:helpers/appuntamento-duration', ['moment'], function (moment) {

    const FALLBACK_FORMAT = 'YYYY-MM-DD HH:mm:ss';

    /**
     * Aggiunge secondi a un datetime sistema Espo (sempre UTC).
     * Non usare toMoment()+format(): scrive l'ora locale come UTC (+2h in estate).
     */
    function addSecondsToSystemDateTime(dateTimeUtil, dateStart, seconds) {
        if (!dateStart) {
            return null;
        }

        const fullFormat = (dateTimeUtil && dateTimeUtil.internalDateTimeFullFormat) || FALLBACK_FORMAT;
        const shortFormat = (dateTimeUtil && dateTimeUtil.internalDateTimeFormat) || 'YYYY-MM-DD HH:mm';

        let m = moment.utc(dateStart, fullFormat, true);

        if (!m.isValid()) {
            m = moment.utc(dateStart, shortFormat, true);
        }

        if (!m.isValid()) {
            m = moment.utc(dateStart);
        }

        if (!m.isValid()) {
            return null;
        }

        return m.add(seconds, 'seconds').format(fullFormat);
    }

    return {
        FALLBACK_DURATION_SECONDS: 5400,
        addSecondsToSystemDateTime: addSecondsToSystemDateTime,
    };
});
