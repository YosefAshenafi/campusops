/**
 * CampusOps Search Module
 * Full-text search with suggestions.
 */
layui.define(['jquery', 'layer', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var common = layui.common;

    var search = {
        debounceTimer: null,

        escapeHtml: function (str) {
            if (!str) return '';
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        },

        init: function () {
            this.bindEvents();
            this.initAutocomplete();
            var user = common.getUser();
            if (user && user.role === 'administrator') {
                $('#btn-rebuild-wrap').show();
            }
        },

        bindEvents: function () {
            var that = this;
            $('#btn-search').on('click', function () { that.doSearch(); });
            $('#search-input').on('keypress', function (e) { if (e.which === 13) that.doSearch(); });
            $('#btn-rebuild-index').on('click', function () { that.rebuildIndex(); });
            // Toggle logistics controls visibility based on search type
            $('#search-type').on('change', function () {
                if ($(this).val() === 'logistics') {
                    $('#logistics-controls').show();
                } else {
                    $('#logistics-controls').hide();
                }
            });
            $('#btn-logistics-search').on('click', function () { that.doLogisticsSearch(); });
        },

        rebuildIndex: function () {
            var btn = $('#btn-rebuild-index');
            btn.attr('disabled', true).text('Rebuilding...');
            common.request({
                url: '/index/rebuild',
                method: 'POST',
                success: function (res) {
                    layer.msg('Index rebuilt successfully', { icon: 1 });
                    btn.attr('disabled', false).text('Rebuild Index');
                },
                error: function () {
                    btn.attr('disabled', false).text('Rebuild Index');
                }
            });
        },

        initAutocomplete: function () {
            var that = this;
            $('#search-input').on('input', function () {
                clearTimeout(that.debounceTimer);
                that.debounceTimer = setTimeout(function () {
                    that.suggest($('#search-input').val());
                }, 300);
            });
        },

        suggest: function (query) {
            if (query.length < 2) {
                $('#search-suggestions').hide();
                return;
            }
            var that = this;
            common.request({
                url: '/search/suggest?q=' + encodeURIComponent(query),
                success: function (res) {
                    if (res.success && res.data.length > 0) {
                        that.renderSuggestions(res.data);
                    }
                }
            });
        },

        renderSuggestions: function (list) {
            var that = this;
            var html = '<div style="background:#fff;border:1px solid #ddd;padding:5px;">';
            for (var i = 0; i < list.length; i++) {
                var item = list[i];
                html += '<div style="padding:5px;cursor:pointer;" onclick="layui.search.loadResult(' + parseInt(item.id, 10) + ',\'' + that.escapeHtml(item.type) + '\')">' +
                    that.escapeHtml(item.title) + ' <span style="color:#999;">(' + that.escapeHtml(item.type) + ')</span></div>';
            }
            html += '</div>';
            $('#search-suggestions').html(html).show();
        },

        doSearch: function (page) {
            page = page || 1;
            var query = $('#search-input').val();
            var type = $('#search-type').val();
            if (query.length < 2) return;

            var that = this;
            var sort = $('#global-sort').val() || 'relevance';
            var author = $('#global-author').val() || '';
            var tags = $('#global-tags').val() || '';
            var replyCountMin = parseInt($('#global-reply-count-min').val(), 10) || 0;
            common.request({
                url: '/search',
                data: { q: query, type: type, page: page, limit: 20, sort: sort, highlight: 1, author: author, tags: tags, reply_count_min: replyCountMin },
                success: function (res) {
                    if (res.success) {
                        that.renderResults(res.data.list);
                        that.renderPagination(res.data.total, res.data.page, res.data.limit);
                        that.checkCorrection(query);
                    }
                }
            });
        },

        renderResults: function (list) {
            var that = this;
            var $container = $('#search-results');
            $container.empty();
            if (!list || list.length === 0) {
                $container.html('<div style="text-align:center;padding:30px;color:#999;">No results found</div>');
                return;
            }
            var html = '<table class="layui-table" lay-skin="line"><thead><tr><th>Type</th><th>Title</th><th>Preview</th><th>Tags</th><th>Author</th><th>Actions</th></tr></thead><tbody>';
            for (var i = 0; i < list.length; i++) {
                var r = list[i];
                var hl = r.highlights || {};
                var titleHtml = hl.title ? hl.title : that.escapeHtml(r.title);
                var bodyHtml = hl.body ? hl.body : that.escapeHtml(r.body || '-');
                var tagsHtml = hl.tags ? hl.tags : that.escapeHtml((r.tags || []).join(', ') || '-');
                var authorHtml = hl.author ? hl.author : that.escapeHtml(r.author || '-');
                html += '<tr>' +
                    '<td><span class="layui-badge layui-bg-blue">' + that.escapeHtml(r.type) + '</span></td>' +
                    '<td>' + titleHtml + '</td>' +
                    '<td>' + bodyHtml + '</td>' +
                    '<td>' + tagsHtml + '</td>' +
                    '<td>' + authorHtml + '</td>' +
                    '<td><button class="layui-btn layui-btn-xs layui-btn-normal" onclick="layui.search.loadResult(' + parseInt(r.id, 10) + ',\'' + that.escapeHtml(r.type) + '\')">View</button></td></tr>';
            }
            html += '</tbody></table>';
            $container.html(html);
        },

        renderPagination: function (total, page, limit) {
            var totalPages = Math.ceil(total / limit);
            var html = '<span>Total: ' + total + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page > 1 ? 'onclick="layui.search.doSearch(' + (page - 1) + ')"' : 'disabled') + '>Prev</button> ';
            html += '<span>Page ' + page + ' of ' + totalPages + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page < totalPages ? 'onclick="layui.search.doSearch(' + (page + 1) + ')"' : 'disabled') + '>Next</button>';
            $('#search-pagination').html(html);
        },

        checkCorrection: function (query) {
            var that = this;
            common.request({
                url: '/search/suggest?q=' + encodeURIComponent(query.substring(0, query.length - 1)),
                success: function (res) {
                    if (res.success && res.data.length > 0 && res.data[0].title !== query) {
                        $('#spell-correction').html('Did you mean: <strong>' + res.data[0].title + '</strong>?').show();
                        $('#spell-correction').on('click', function () {
                            $('#search-input').val(res.data[0].title);
                            that.doSearch();
                        });
                    } else {
                        $('#spell-correction').hide();
                    }
                }
            });
        },

        doLogisticsSearch: function (page) {
            page = page || 1;
            var query = $('#search-input').val();
            if (query.length < 2) return;

            var that = this;
            common.request({
                url: '/search/logistics',
                data: {
                    q: query,
                    page: page,
                    limit: 20,
                    sort: $('#logistics-sort').val() || 'recency',
                    status: $('#logistics-status').val() || '',
                    carrier: $('#logistics-carrier').val() || ''
                },
                success: function (res) {
                    if (res.success) {
                        that.renderResults(res.data.list);
                        that.renderPagination(res.data.total, res.data.page, res.data.limit);
                    }
                }
            });
        },

        loadResult: function (id, type) {
            var $container = layui.jquery('#app-content-inner');
            $container.empty();
            if (type === 'activity') {
                layui.use('activities', function () {
                    layui.activities.showDetail(id);
                });
            } else if (type === 'order') {
                layui.use('orders', function () {
                    layui.orders.showDetail(id);
                });
            }
        }
    };

    window.layui = window.layui || {};
    window.layui.search = search;
    exports('search', search);
});