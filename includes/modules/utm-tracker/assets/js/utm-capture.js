/**
 * Leyka UTM Tracker — client-side UTM cookie capture.
 *
 * Works on cached pages where PHP setcookie() never fires.
 * Sets the same cookies that class-utm.php sets server-side.
 */
(function () {
    var params = new URLSearchParams(window.location.search);

    var source   = params.get('utm_source')   || '';
    var medium   = params.get('utm_medium')   || '';
    var campaign = params.get('utm_campaign') || '';

    if (!source) {
        return;
    }

    var expires = new Date();
    expires.setTime(expires.getTime() + 30 * 24 * 60 * 60 * 1000);
    var expStr = ';expires=' + expires.toUTCString();
    var path   = ';path=/';
    var secure = location.protocol === 'https:' ? ';secure' : '';
    var same   = ';samesite=Lax';

    function setCookie(name, value) {
        document.cookie = name + '=' + encodeURIComponent(value) + expStr + path + secure + same;
    }

    function getCookie(name) {
        var match = document.cookie.match(new RegExp('(?:^|; )' + name + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : '';
    }

    // FIRST touch: set all three atomically, only if first_source is not yet set.
    if (!getCookie('leyka_utm_first_source')) {
        setCookie('leyka_utm_first_source', source);
        setCookie('leyka_utm_first_medium', medium);
        setCookie('leyka_utm_first_campaign', campaign);
    }

    // LAST touch: always overwrite all three together (atomic).
    setCookie('leyka_utm_last_source', source);
    setCookie('leyka_utm_last_medium', medium);
    setCookie('leyka_utm_last_campaign', campaign);
})();
