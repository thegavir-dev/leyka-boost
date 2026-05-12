/**
 * Leyka UTM Tracker — UTM Link Generator.
 *
 * Client-side URL generation, clipboard copy, AJAX history.
 */
(function ($) {
    'use strict';

    var $url      = $('#lutm-gen-url');
    var $source   = $('#lutm-gen-source');
    var $medium   = $('#lutm-gen-medium');
    var $campaign = $('#lutm-gen-campaign');
    var $content  = $('#lutm-gen-content');
    var $term     = $('#lutm-gen-term');
    var $output   = $('#lutm-gen-output');
    var $result   = $('.lutm-gen-result');
    var $body     = $('#lutm-gen-history-body');

    // ── Generate ────────────────────────────────────────────────

    $('#lutm-gen-generate').on('click', function () {
        var base = $.trim($url.val());

        if (!base) {
            alert(lutmGen.i18n.errorUrlEmpty);
            $url.focus();
            return;
        }

        if (!/^https?:\/\/.+/i.test(base)) {
            alert(lutmGen.i18n.errorUrlInvalid);
            $url.focus();
            return;
        }

        var source = sanitize($source.val());
        if (!source) {
            alert(lutmGen.i18n.errorSourceEmpty);
            $source.focus();
            return;
        }

        var params = [];
        params.push('utm_source=' + encodeURIComponent(source));

        var medium = sanitize($medium.val());
        if (medium)   params.push('utm_medium='   + encodeURIComponent(medium));

        var campaign = sanitize($campaign.val());
        if (campaign) params.push('utm_campaign=' + encodeURIComponent(campaign));

        var content = sanitize($content.val());
        if (content)  params.push('utm_content='  + encodeURIComponent(content));

        var term = sanitize($term.val());
        if (term)     params.push('utm_term='     + encodeURIComponent(term));

        var separator = base.indexOf('?') === -1 ? '?' : '&';
        var fullUrl = base + separator + params.join('&');

        $output.val(fullUrl);
        $result.show();

        // Save to history via AJAX.
        $.post(lutmGen.ajaxUrl, {
            action:   'lutm_gen_save',
            _nonce:   lutmGen.nonce,
            url:      base,
            source:   source,
            medium:   medium,
            campaign: campaign,
            content:  content,
            term:     term,
            full_url: fullUrl
        }, function (res) {
            if (res.success) {
                renderHistory(res.data);
            }
        });
    });

    // ── Copy ────────────────────────────────────────────────────

    $('#lutm-gen-copy').on('click', function () {
        var text = $output.val();
        if (!text) return;

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(showCopied);
        } else {
            $output[0].select();
            document.execCommand('copy');
            showCopied();
        }
    });

    function showCopied() {
        var $notice = $('#lutm-gen-copied');
        $notice.fadeIn(200);
        setTimeout(function () { $notice.fadeOut(300); }, 1500);
    }

    // ── Clear form ──────────────────────────────────────────────

    $('#lutm-gen-clear').on('click', function () {
        $url.val('');
        $source.val('');
        $medium.val('');
        $campaign.val('');
        $content.val('');
        $term.val('');
        $output.val('');
        $result.hide();
    });

    // ── Clear history ───────────────────────────────────────────

    $('#lutm-gen-clear-history').on('click', function () {
        if (!confirm(lutmGen.i18n.confirmClear)) return;

        $.post(lutmGen.ajaxUrl, {
            action: 'lutm_gen_clear',
            _nonce: lutmGen.nonce
        }, function (res) {
            if (res.success) {
                renderHistory([]);
            }
        });
    });

    // ── History table ───────────────────────────────────────────

    function renderHistory(rows) {
        if (!rows || !rows.length) {
            $body.html('<tr><td colspan="7">' + escHtml(lutmGen.i18n.noHistory) + '</td></tr>');
            return;
        }

        var html = '';
        for (var i = 0; i < rows.length; i++) {
            var r = rows[i];
            var short = r.full_url.length > 60 ? r.full_url.substring(0, 57) + '…' : r.full_url;

            html += '<tr>';
            html += '<td title="' + escAttr(r.full_url) + '">' + escHtml(short) + '</td>';
            html += '<td>' + escHtml(r.source)  + '</td>';
            html += '<td>' + escHtml(r.medium)  + '</td>';
            html += '<td>' + escHtml(r.campaign) + '</td>';
            html += '<td>' + escHtml(r.user)     + '</td>';
            html += '<td>' + escHtml(r.created_at) + '</td>';
            html += '<td>';
            html += '<a href="#" class="lutm-hist-copy" data-url="' + escAttr(r.full_url) + '">' + escHtml(lutmGen.i18n.copy) + '</a>';
            html += ' · ';
            html += '<a href="#" class="lutm-hist-load" data-idx="' + i + '">' + escHtml(lutmGen.i18n.load) + '</a>';
            html += '</td>';
            html += '</tr>';
        }

        $body.html(html);
        $body.data('rows', rows);
    }

    // Delegated events for history actions.
    $body.on('click', '.lutm-hist-copy', function (e) {
        e.preventDefault();
        var text = $(this).data('url');
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(showCopied);
        } else {
            $output.val(text);
            $output[0].select();
            document.execCommand('copy');
            showCopied();
        }
    });

    $body.on('click', '.lutm-hist-load', function (e) {
        e.preventDefault();
        var idx  = $(this).data('idx');
        var rows = $body.data('rows');
        if (!rows || !rows[idx]) return;

        var r = rows[idx];
        $url.val(r.url);
        $source.val(r.source);
        $medium.val(r.medium);
        $campaign.val(r.campaign);
        $content.val(r.content || '');
        $term.val(r.term || '');
        $output.val(r.full_url);
        $result.show();

        $('html, body').animate({ scrollTop: 0 }, 300);
    });

    // ── Helpers ──────────────────────────────────────────────────

    function sanitize(val) {
        return $.trim(val).replace(/\s+/g, '_');
    }

    function escHtml(s) {
        if (!s) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(s));
        return div.innerHTML;
    }

    function escAttr(s) {
        return escHtml(s).replace(/"/g, '&quot;');
    }

    // ── Init: load history on page load ─────────────────────────

    $.post(lutmGen.ajaxUrl, {
        action: 'lutm_gen_load',
        _nonce: lutmGen.nonce
    }, function (res) {
        if (res.success) {
            renderHistory(res.data);
        }
    });

})(jQuery);
