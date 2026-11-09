/* FastFootBalInfo — AJAX 30 giây cho bảng tỉ số (danh sách dạng hàng). */
(function ($) {
    'use strict';

    var CFG = window.FFBI || {};

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function ffFlash(message, type) {
        var cls = 'alert alert-' + (type || 'danger') +
            ' position-fixed top-0 start-50 translate-middle-x mt-3 shadow';
        var $el = $('<div></div>').addClass(cls).attr('role', 'alert')
            .css({ zIndex: 2000, maxWidth: '90vw' }).text(message);
        $('body').append($el);
        window.setTimeout(function () {
            $el.fadeOut(300, function () { $(this).remove(); });
        }, 4000);
    }

    function initials(name) {
        var t = String(name || '').trim();
        return t ? t.charAt(0).toUpperCase() : '?';
    }

    function isLive(tt) { return tt === 'DANG_DA' || tt === 'NGHI_GIUA_HIEP'; }
    function isPending(tt) { return tt === 'CHO_CAP_NHAT'; }

    function chipClass(tt) {
        return ({
            'DANG_DA': 'ff-chip ff-chip--live',
            'NGHI_GIUA_HIEP': 'ff-chip ff-chip--warn',
            'CHO_CAP_NHAT': 'ff-chip ff-chip--warn',
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
            'CHO_CAP_NHAT': 'as-chip warn',
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
        var pending = isPending(m.trang_thai);
        var soon = m.trang_thai === 'SAP_DIEN_RA';
        var upcoming = soon && m.thoi_gian_bat_dau
            && new Date(String(m.thoi_gian_bat_dau).replace(' ', 'T') + 'Z').getTime() > Date.now();
        var phut = (live && m.phut > 0) ? (m.phut + "'") : '';
        var time = (live && phut) ? '<span class="min">' + esc(phut) + '</span>'
            : '<span>' + esc(m.thoi_gian_hien_thi) + '</span>';
        var score = soon ? 'vs' : (pending ? '–' : (m.ti_so_nha + ' - ' + m.ti_so_khach));
        var scoreTitle = pending
            ? ' title="' + (Number(m.co_live) === 0 ? 'ESPN không cung cấp live cho trận này' : 'Chờ nguồn cập nhật tỉ số') + '"'
            : '';
        return '<div class="as-row' + (live ? ' is-live' : '') + '" data-id="' + m.tran_dau_id + '">'
            + '<div class="as-time">' + time + '</div>'
            + '<div class="as-team home"><a class="nm" href="' + doiHref(m.doi_nha_id) + '">' + esc(m.doi_nha_ten) + '</a>'
            + crest(m.doi_nha_ten, m.doi_nha_logo, 'ink', doiHref(m.doi_nha_id), true, 20) + '</div>'
            + '<a class="as-score' + (soon ? ' soon' : '') + '"' + scoreTitle + ' href="' + tranHref(m.tran_dau_id) + '">' + score + '</a>'
            + '<div class="as-team away">' + crest(m.doi_khach_ten, m.doi_khach_logo, 'muted', doiHref(m.doi_khach_id), true, 20)
            + '<a class="nm" href="' + doiHref(m.doi_khach_id) + '">' + esc(m.doi_khach_ten) + '</a></div>'
            + '<div class="as-status"><span class="' + rowChip(m.trang_thai) + '">'
            + (live ? '<span class="as-live-dot"></span>' : '') + esc(m.trang_thai_nhan) + '</span></div>'
            + '<div class="as-actions">'
            + (upcoming ? '<button type="button" class="as-mini js-calendar" data-tran="' + m.tran_dau_id + '" data-field="nhac" data-on="0" title="Nhắc tôi" aria-label="Nhắc tôi"><i class="bi bi-bell"></i></button>'
                + '<button type="button" class="as-mini js-calendar" data-tran="' + m.tran_dau_id + '" data-field="trong_lich" data-on="0" title="Thêm vào lịch" aria-label="Thêm vào lịch"><i class="bi bi-calendar-plus"></i></button>' : '')
            + '</div>'
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
                hydrateCalendarButtons();
                if (res.meta && res.meta.cap_nhat_luc) {
                    $('#cap-nhat-luc').text(res.meta.cap_nhat_luc);
                }
                if (res.meta && res.meta.dem) {
                    Object.keys(res.meta.dem).forEach(function (key) {
                        $('[data-tab-count="' + key + '"]').text('(' + res.meta.dem[key] + ')');
                        $('[data-dem="' + key + '"]').text(res.meta.dem[key]);
                    });
                }
                $list.find('.as-state').remove();
                if (res.meta && res.meta.du_lieu_cu && res.meta.thong_bao) {
                    $list.prepend('<div class="as-state"><i class="bi bi-exclamation-triangle"></i> ' +
                        esc(res.meta.thong_bao) + '</div>');
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
                var live = isLive(m.trang_thai);
                var pending = isPending(m.trang_thai);
                $('#ty-so').text(m.trang_thai === 'SAP_DIEN_RA' ? 'vs'
                    : (pending ? '–' : (m.ti_so_nha + ' - ' + m.ti_so_khach)))
                    .attr('title', pending
                        ? (Number(m.co_live) === 0 ? 'ESPN không cung cấp live cho trận này' : 'Chờ nguồn cập nhật tỉ số')
                        : '');
                var phut = (live && m.phut > 0) ? (' · ' + m.phut + "'") : '';
                $('#trang-thai-badge')
                    .attr('class', chipClass(m.trang_thai))
                    .html((live ? '<span class="ff-live-dot"></span>' : '') + esc(m.trang_thai_nhan) + esc(phut));
                $('#cap-nhat-luc').text((res.meta && res.meta.cap_nhat_luc) || '');
            });
    }

    function formatMoney(value) {
        var number = Number(value || 0);
        try {
            return number.toLocaleString('vi-VN') + ' ₫';
        } catch (ignored) {
            return String(number).replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' ₫';
        }
    }

    function refreshProductPrices() {
        var $grid = $('#sp-grid');
        if (!$grid.length) { return; }
        var $form = $('.sp-toolbar');
        var data = {
            action: 'prices',
            danh_muc: $form.find('input[name="danh_muc"]').val() || '',
            ten_mien: $form.find('input[name="ten_mien"]').val() || '',
            q: $('#sp-q').val() || ''
        };
        $.ajax({ url: CFG.apiProducts, data: data, dataType: 'json' })
            .done(function (res) {
                if (!res || !res.ok || !res.data) { return; }
                var latest = '';
                res.data.forEach(function (row) {
                    var $card = $grid.find('.sp-card[data-sp-id="' + row.ma + '"]');
                    if (!$card.length) { return; }
                    var price = Number(row.gia_hien_tai || 0);
                    var original = Number(row.gia_goc || 0);
                    var percent = Number(row.phan_tram_giam || 0);
                    var inStock = Number(row.con_hang) === 1;
                    var hasDiscount = original > price;
                    $card.attr('data-sp-current', price)
                        .attr('data-sp-original', original)
                        .attr('data-sp-discount', percent)
                        .attr('data-sp-stock', inStock ? 1 : 0);
                    $card.find('[data-sp-price]').text(formatMoney(price));
                    $card.find('[data-sp-original-price]').text(formatMoney(original)).toggleClass('d-none', !hasDiscount);
                    $card.find('[data-sp-save]').text('Tiết kiệm ' + formatMoney(row.so_tien_giam || 0)).toggleClass('d-none', !hasDiscount);
                    $card.find('[data-sp-discount-label]').text('-' + percent + '%').toggleClass('d-none', percent <= 0);
                    $card.find('[data-sp-out]').toggleClass('d-none', inStock);
                    $card.find('[data-sp-stock-off]').toggleClass('d-none', inStock);
                    $card.find('[data-sp-updated]').text(row.lan_kiem_tra || 'Đang cập nhật');
                    if (row.lan_kiem_tra && row.lan_kiem_tra > latest) {
                        latest = row.lan_kiem_tra;
                    }
                });
                if (latest) {
                    $('#sp-cap-nhat').text(latest);
                }
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
                        $btn.closest('.list-group-item').fadeOut(200, function () {
                            $(this).remove();
                            if ($('.list-group-item').length === 0) {
                                $('#fav-empty').prop('hidden', false);
                            }
                        });
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
                    ffFlash(msg, 'danger');
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

    function updateCalendarButtons(tranId, trongLich, nhac) {
        var $buttons = $('.js-calendar[data-tran="' + tranId + '"]');
        $buttons.each(function () {
            var $btn = $(this);
            var field = $btn.data('field');
            var on = field === 'nhac' ? !!nhac : !!trongLich;
            $btn.data('on', on ? '1' : '0').toggleClass('is-on', on);
            if (field === 'nhac') {
                $btn.find('i').attr('class', 'bi ' + (on ? 'bi-bell-fill' : 'bi-bell'));
                $btn.find('span').text(on ? 'Đã nhắc' : 'Nhắc tôi');
            } else {
                $btn.find('i').attr('class', 'bi ' + (on ? 'bi-calendar-check-fill' : 'bi-calendar-plus'));
                $btn.find('span').text(on ? 'Trong lịch' : 'Thêm vào lịch');
            }
        });
    }

    function bindCalendar() {
        $(document).on('click', '.js-calendar', function (e) {
            e.preventDefault();
            if (!CFG.loggedIn) {
                window.location.href = (CFG.base || '') + '/index.php?r=dangnhap&msg=can-dang-nhap';
                return;
            }
            var $btn = $(this);
            var tranId = $btn.data('tran');
            var field = $btn.data('field');
            var on = parseInt($btn.data('on'), 10) === 1;
            var $same = $('.js-calendar[data-tran="' + tranId + '"]');
            var trongLich = parseInt($same.filter('[data-field="trong_lich"]').data('on'), 10) === 1;
            var nhac = parseInt($same.filter('[data-field="nhac"]').data('on'), 10) === 1;
            if (field === 'nhac') {
                nhac = !on;
            } else {
                trongLich = !on;
            }
            $same.prop('disabled', true);
            $.post(CFG.apiCalendar, {
                tran: tranId,
                trong_lich: trongLich ? '1' : '0',
                nhac: nhac ? '1' : '0',
                _csrf: CFG.csrf
            }, null, 'json')
                .done(function (res) {
                    if (!res || !res.ok) {
                        ffFlash((res && res.meta && res.meta.thong_bao) || 'Không thể cập nhật lịch.', 'danger');
                        return;
                    }
                    updateCalendarButtons(tranId, !!res.data.trong_lich, !!res.data.nhac);
                    ffFlash((res.meta && res.meta.thong_bao) || 'Đã cập nhật lịch.', 'success');
                    if ($('[data-calendar-page]').length) {
                        window.setTimeout(function () { window.location.reload(); }, 500);
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Không thể cập nhật lịch.';
                    try { msg = JSON.parse(xhr.responseText).meta.thong_bao || msg; } catch (ignored) {}
                    if (xhr.status === 401) {
                        window.location.href = (CFG.base || '') + '/index.php?r=dangnhap&msg=can-dang-nhap';
                        return;
                    }
                    ffFlash(msg, 'danger');
                })
                .always(function () { $same.prop('disabled', false); });
        });
    }

    function hydrateCalendarButtons() {
        if (!CFG.loggedIn || !CFG.apiCalendar) { return; }
        var ids = [];
        $('.js-calendar[data-tran]').each(function () {
            var id = String($(this).data('tran') || '');
            if (id && ids.indexOf(id) === -1) { ids.push(id); }
        });
        if (!ids.length) { return; }
        $.getJSON(CFG.apiCalendar, { action: 'states', ids: ids.join(',') }, function (res) {
            if (!res || !res.ok || !res.data) { return; }
            var items = res.data.items || {};
            Object.keys(items).forEach(function (id) {
                var state = items[id] || {};
                updateCalendarButtons(id, !!state.trong_lich, !!state.nhac);
            });
        });
    }

    function bindNotifications() {
        if (!CFG.loggedIn || !CFG.apiNotifications) { return; }
        var lastUnread = null;

        function formatTime(value) {
            try {
                var d = new Date(String(value || '').replace(' ', 'T') + 'Z');
                if (isNaN(d.getTime())) { return String(value || ''); }
                return d.toLocaleString('vi-VN');
            } catch (ignored) {
                return String(value || '');
            }
        }

        function render(items, unread) {
            var $badge = $('[data-notify-count]');
            if (unread > 0) {
                $badge.text(unread > 9 ? '9+' : String(unread)).prop('hidden', false);
            } else {
                $badge.prop('hidden', true);
            }
            var $list = $('[data-notify-list]');
            if (!$list.length) { return; }
            if (!items || !items.length) {
                $list.html('<li><span class="dropdown-item-text text-muted small">Chưa có thông báo.</span></li>');
                return;
            }
            var html = '<li class="dropdown-header d-flex justify-content-between align-items-center">'
                + '<span>Thông báo</span>'
                + '<button type="button" class="btn btn-link btn-sm p-0" data-notify-read-all>Đọc tất cả</button></li>';
            items.forEach(function (n) {
                var href = (CFG.base || '') + '/index.php?r=tran&id=' + encodeURIComponent(n.tran_dau_id || '');
                html += '<li><a class="dropdown-item' + (Number(n.da_doc) ? '' : ' fw-semibold') + '" href="' + href + '">'
                    + '<span class="d-block">' + esc(n.tieu_de) + '</span>'
                    + '<small class="text-muted d-block">' + esc(n.noi_dung) + '</small>'
                    + '<small class="text-muted">' + esc(formatTime(n.ngay_tao)) + '</small></a></li>';
            });
            $list.html(html);
        }

        function refresh() {
            $.getJSON(CFG.apiNotifications, { action: 'list' }, function (res) {
                if (!res || !res.ok) { return; }
                var unread = Number((res.meta && res.meta.chua_doc) || 0);
                var items = (res.data && res.data.items) || [];
                render(items, unread);
                if (lastUnread !== null && unread > lastUnread && items.length) {
                    ffFlash(items[0].tieu_de + ': ' + items[0].noi_dung, 'info');
                }
                lastUnread = unread;
            });
        }

        $(document).on('click', '[data-notify-toggle], [data-notify-read-all]', function () {
            $.post(CFG.apiNotifications, { action: 'read', _csrf: CFG.csrf }, null, 'json')
                .done(function (res) {
                    if (res && res.ok) {
                        $('[data-notify-count]').prop('hidden', true);
                        $('[data-notify-list] .fw-semibold').removeClass('fw-semibold');
                        lastUnread = 0;
                        window.setTimeout(refresh, 300);
                    }
                });
        });

        refresh();
        window.setInterval(function () {
            if (!document.hidden) { refresh(); }
        }, 30000);
    }

    $(function () {
        bindFavorite();
        bindSync();
        bindCalendar();
        bindNotifications();
        hydrateCalendarButtons();

        var coHome = $('#as-list').length > 0;
        var coChiTiet = $('#chi-tiet-tran').length > 0;
        var coSanPham = $('#sp-grid').length > 0;

        function tick() {
            if (document.hidden) { return; }
            if (coHome) { refreshHome(); }
            if (coChiTiet) { refreshMatch(); }
        }
        if (coHome || coChiTiet) {
            setInterval(tick, (CFG.chuKyGiay || 30) * 1000);
        }
        if (coSanPham) {
            refreshProductPrices();
            setInterval(function () {
                if (!document.hidden) { refreshProductPrices(); }
            }, (CFG.chuKyGiaGiay || 60) * 1000);
        }
        if (coHome || coChiTiet || coSanPham) {
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) { return; }
                if (coHome || coChiTiet) { tick(); }
                if (coSanPham) { refreshProductPrices(); }
            });
        }
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

})();

// Tab trận đấu: cập nhật trạng thái active khi bấm anchor nội bộ.
(function () {
    'use strict';
    var tabs = document.querySelector('.match-tabs');
    if (!tabs) { return; }
    tabs.addEventListener('click', function (event) {
        var link = event.target.closest('a[href^="#"]');
        if (!link) { return; }
        tabs.querySelectorAll('a').forEach(function (item) { item.classList.remove('is-active'); });
        link.classList.add('is-active');
    });
})();
