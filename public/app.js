/* FastFootBalInfo — AJAX 30 giây cho bảng tỉ số (danh sách dạng hàng). */
(function ($) {
    'use strict';

    var CFG = window.FFBI || {};

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function initials(name) {
        var t = String(name || '').trim();
        return t ? t.charAt(0).toUpperCase() : '?';
    }

    function isLive(tt) { return tt === 'DANG_DA' || tt === 'NGHI_GIUA_HIEP'; }

    function chipClass(tt) {
        return ({
            'DANG_DA': 'ff-chip ff-chip--live',
            'NGHI_GIUA_HIEP': 'ff-chip ff-chip--warn',
            'SAP_DIEN_RA': 'ff-chip ff-chip--soon',
            'KET_THUC': 'ff-chip ff-chip--ft',
            'HOAN': 'ff-chip ff-chip--muted',
            'HUY': 'ff-chip ff-chip--muted'
        })[tt] || 'ff-chip ff-chip--muted';
    }

    function rowChip(tt) {
        return ({
            'DANG_DA': 'as-chip live',
            'NGHI_GIUA_HIEP': 'as-chip warn',
            'SAP_DIEN_RA': 'as-chip soon',
            'KET_THUC': 'as-chip ft'
        })[tt] || 'as-chip muted';
    }

    function doiHref(id) { return (CFG.base || '') + '/index.php?r=doi-chitiet&id=' + id; }
    function tranHref(id) { return (CFG.base || '') + '/index.php?r=tran&id=' + id; }

    function crest(name, logo, variant, href, sm, size) {
        var cls = 'ff-crest ff-crest--' + (variant === 'muted' ? 'muted' : 'ink') + (sm ? ' ff-crest--sm' : '');
        var inner = '<span class="ff-crest__ini">' + esc(initials(name)) + '</span>';
        if (logo) {
            inner += '<img class="ff-crest__img" src="' + esc(logo) + '" alt="" loading="lazy"'
                + ' referrerpolicy="no-referrer" onerror="this.remove()">';
        }
        var stylesize = size ? ' style="width:' + size + 'px;height:' + size + 'px"' : '';
        var tag = href ? 'a' : 'span';
        var attr = href ? ' href="' + href + '"' : '';
        return '<' + tag + ' class="' + cls + '"' + attr + stylesize + '>' + inner + '</' + tag + '>';
    }

    function rowHtml(m) {
        var live = isLive(m.trang_thai);
        var soon = m.trang_thai === 'SAP_DIEN_RA';
        var phut = (live && m.phut > 0) ? (m.phut + "'") : '';
        var time = (live && phut) ? '<span class="min">' + esc(phut) + '</span>'
            : '<span>' + esc(m.thoi_gian_hien_thi) + '</span>';
        var score = soon ? 'vs' : (m.ti_so_nha + ' - ' + m.ti_so_khach);
        return '<div class="as-row' + (live ? ' is-live' : '') + '" data-id="' + m.tran_dau_id + '">'
            + '<div class="as-time">' + time + '</div>'
            + '<div class="as-team home"><a class="nm" href="' + doiHref(m.doi_nha_id) + '">' + esc(m.doi_nha_ten) + '</a>'
            + crest(m.doi_nha_ten, m.doi_nha_logo, 'ink', doiHref(m.doi_nha_id), true, 20) + '</div>'
            + '<a class="as-score' + (soon ? ' soon' : '') + '" href="' + tranHref(m.tran_dau_id) + '">' + score + '</a>'
            + '<div class="as-team away">' + crest(m.doi_khach_ten, m.doi_khach_logo, 'muted', doiHref(m.doi_khach_id), true, 20)
            + '<a class="nm" href="' + doiHref(m.doi_khach_id) + '">' + esc(m.doi_khach_ten) + '</a></div>'
            + '<div class="as-status"><span class="' + rowChip(m.trang_thai) + '">'
            + (live ? '<span class="as-live-dot"></span>' : '') + esc(m.trang_thai_nhan) + '</span></div>'
            + '</div>';
    }

    function listHtml(items) {
        if (!items || !items.length) {
            return '<div class="as-empty"><i class="bi bi-search fs-4 d-block mb-2"></i> Không có trận nào phù hợp.</div>';
        }
        var html = '';
        var last = null;
        items.forEach(function (m) {
            if (m.giai_ten !== last) {
                last = m.giai_ten;
                html += '<div class="as-league-head"><span class="ic ff-crest ff-crest--muted ff-crest--sm">'
                    + '<span class="ff-crest__ini">' + esc(initials(m.giai_ten)) + '</span></span>'
                    + '<span class="name">' + esc(m.giai_ten) + '</span></div>';
            }
            html += rowHtml(m);
        });
        return html;
    }

    function refreshHome() {
        var $list = $('#as-list');
        if (!$list.length) { return; }
        var data = {
            view: 'home',
            tt: $list.data('tt') || 'all',
            giai: $list.data('giai') || '',
            page: $list.data('trang') || 1
        };
        $.ajax({ url: CFG.apiMatches, data: data, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.ok) { return; }
                $list.html(listHtml(res.data.items));
                if (res.meta && res.meta.cap_nhat_luc) {
                    $('#cap-nhat-luc').text(res.meta.cap_nhat_luc);
                }
            });
    }

    function refreshMatch() {
        var $box = $('#chi-tiet-tran');
        if (!$box.length) { return; }
        $.ajax({ url: CFG.apiMatch, data: { id: $box.data('id') }, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.ok) { return; }
                var m = res.data.tran;
                $('#ty-so').text(m.trang_thai === 'SAP_DIEN_RA' ? 'vs' : (m.ti_so_nha + ' - ' + m.ti_so_khach));
                var live = isLive(m.trang_thai);
                var phut = (live && m.phut > 0) ? (' · ' + m.phut + "'") : '';
                $('#trang-thai-badge')
                    .attr('class', chipClass(m.trang_thai))
                    .html((live ? '<span class="ff-live-dot"></span>' : '') + esc(m.trang_thai_nhan) + esc(phut));
                $('#cap-nhat-luc').text((res.meta && res.meta.cap_nhat_luc) || '');
            });
    }

    function bindFavorite() {
        $(document).on('click', '.fav-btn', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var doi = $btn.data('doi');
            var bat = parseInt($btn.data('bat'), 10) === 0 ? 0 : 1;
            var isStar = $btn.data('icon') === 'star';
            $.post(CFG.apiFavorite, { doi: doi, bat: bat, _csrf: CFG.csrf }, null, 'json')
                .done(function (res) {
                    if (!res || !res.ok) { return; }
                    var yeuThich = res.data.yeu_thich;
                    $btn.data('bat', yeuThich ? '0' : '1');
                    $btn.toggleClass('on', yeuThich).toggleClass('is-on', yeuThich);
                    $btn.toggleClass('btn-danger', yeuThich).toggleClass('btn-outline-danger', !yeuThich);
                    var icon = $btn.find('i');
                    if (isStar) {
                        icon.attr('class', yeuThich ? 'bi bi-star-fill' : 'bi bi-star');
                    } else {
                        icon.attr('class', yeuThich ? 'bi bi-heart-fill text-danger' : 'bi bi-heart text-danger');
                        $btn.find('.fav-text').text(yeuThich ? 'Đang yêu thích' : 'Thêm yêu thích');
                    }
                    if (!yeuThich && $btn.closest('.list-group').length) {
                        $btn.closest('.list-group-item').fadeOut(200, function () { $(this).remove(); });
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Không thực hiện được.';
                    try { msg = JSON.parse(xhr.responseText).meta.thong_bao || msg; } catch (ignored) {}
                    if (xhr.status === 401) {
                        msg = 'Vui lòng đăng nhập.';
                        setTimeout(function () {
                            window.location.href = (CFG.base || '') + '/index.php?r=dangnhap&msg=can-dang-nhap';
                        }, 800);
                    }
                    alert(msg);
                });
        });
    }

    function bindSync() {
        $(document).on('submit', '.sync-form', function (e) {
            e.preventDefault();
            var loai = $(this).data('loai');
            var $box = $('#sync-result').removeClass('d-none alert-info alert-success alert-warning alert-danger').addClass('alert-info');
            $box.text('Đang đồng bộ ' + loai + '...');
            $.post(CFG.apiSync, { loai: loai, _csrf: CFG.csrf }, null, 'json')
                .done(function (res) {
                    if (res && res.ok) {
                        var ok = res.data.ma === 'THANH_CONG' || res.data.ma === 'RONG';
                        $box.removeClass('alert-info').addClass(ok ? 'alert-success' : 'alert-warning')
                            .text(res.data.ma + ': ' + ((res.meta && res.meta.thong_bao) || ''));
                    } else {
                        $box.removeClass('alert-info').addClass('alert-danger').text((res.meta && res.meta.thong_bao) || 'Lỗi.');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Lỗi khi đồng bộ.';
                    try { msg = JSON.parse(xhr.responseText).meta.thong_bao || msg; } catch (ignored) {}
                    $box.removeClass('alert-info').addClass('alert-danger').text(msg);
                });
        });
    }

    $(function () {
        bindFavorite();
        bindSync();

        var coHome = $('#as-list').length > 0;
        var coChiTiet = $('#chi-tiet-tran').length > 0;
        if (!coHome && !coChiTiet) { return; }

        function tick() {
            if (document.hidden) { return; }
            if (coHome) { refreshHome(); }
            if (coChiTiet) { refreshMatch(); }
        }
        setInterval(tick, (CFG.chuKyGiay || 30) * 1000);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) { tick(); }
        });
    });
})(jQuery);


// Đồng hồ đếm ngược cho trận tâm điểm trên trang chủ.
(function () {
    'use strict';
    var card = document.querySelector('[data-countdown-target]');
    if (!card) { return; }
    var target = Number(card.getAttribute('data-countdown-target')) * 1000;
    function update() {
        var seconds = Math.max(0, Math.floor((target - Date.now()) / 1000));
        var units = {
            days: Math.floor(seconds / 86400),
            hours: Math.floor((seconds % 86400) / 3600),
            minutes: Math.floor((seconds % 3600) / 60),
            seconds: seconds % 60
        };
        Object.keys(units).forEach(function (key) {
            var value = card.querySelector('[data-countdown="' + key + '"]');
            if (value) { value.textContent = String(units[key]).padStart(2, '0'); }
        });
    }
    update();
    window.setInterval(update, 1000);

    var reminder = card.querySelector('[data-match-reminder]');
    if (reminder) {
        reminder.addEventListener('click', function () {
            var start = reminder.getAttribute('data-start');
            var endDate = new Date(Date.parse(start.slice(0, 4) + '-' + start.slice(4, 6) + '-' + start.slice(6, 8) + 'T' + start.slice(9, 11) + ':' + start.slice(11, 13) + ':' + start.slice(13, 15) + 'Z') + 7200000);
            var end = endDate.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
            var ics = [
                'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//FastFootBallInfo//Match Centre//VI',
                'BEGIN:VEVENT', 'UID:liverpool-manchester-united-20261122@fastfootballinfo',
                'DTSTAMP:' + new Date().toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''),
                'DTSTART:' + start, 'DTEND:' + end,
                'SUMMARY:Liverpool vs Manchester United', 'LOCATION:Anfield',
                'DESCRIPTION:Premier League - FastFootBallInfo',
                'BEGIN:VALARM', 'TRIGGER:-PT30M', 'ACTION:DISPLAY',
                'DESCRIPTION:Trận Liverpool vs Manchester United sẽ bắt đầu sau 30 phút.',
                'END:VALARM', 'END:VEVENT', 'END:VCALENDAR'
            ].join('\r\n');
            var blobUrl = URL.createObjectURL(new Blob([ics], { type: 'text/calendar;charset=utf-8' }));
            var download = document.createElement('a');
            download.href = blobUrl;
            download.download = 'liverpool-man-utd-reminder.ics';
            document.body.appendChild(download);
            download.click();
            download.remove();
            window.setTimeout(function () { URL.revokeObjectURL(blobUrl); }, 1000);
        });
    }
})();

// aaa
// bbbbbbv
