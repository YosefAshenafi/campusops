/**
 * CampusOps Orders Module
 * Order management and state machine visualization.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var common = layui.common;

    var orders = {
        currentPage: 1,
        currentOrder: null,
        userInfo: null,

        initList: function () {
            this.loadOrders();
            this.bindListEvents();
        },

        loadOrders: function (page) {
            page = page || 1;
            var that = this;
            common.request({
                url: '/orders',
                data: { page: page, limit: 20, state: $('#filter-state').val() || '' },
                success: function (res) {
                    if (res.success) {
                        that.renderTable(res.data.list);
                        that.renderPagination(res.data.total, res.data.page, res.data.limit);
                    }
                }
            });
        },

        renderTable: function (list) {
            var $tbody = $('#orders-tbody');
            $tbody.empty();
            if (!list || list.length === 0) {
                $tbody.append('<tr><td colspan="7" style="text-align:center;color:#999;">No orders</td></tr>');
                return;
            }
            for (var i = 0; i < list.length; i++) {
                var o = list[i];
                $tbody.append('<tr>' +
                    '<td>' + o.id + '</td>' +
                    '<td>' + (o.activity_title || 'Activity #' + o.activity_id) + '</td>' +
                    '<td>' + this.getStateBadge(o.state) + '</td>' +
                    '<td>$' + o.amount + '</td>' +
                    '<td>' + (o.ticket_number || '-') + '</td>' +
                    '<td>' + common.formatDateTime(o.created_at) + '</td>' +
                    '<td><button class="layui-btn layui-btn-xs" data-action="view" data-id="' + o.id + '">View</button></td>' +
                    '</tr>');
            }
        },

        renderPagination: function (total, page, limit) {
            var totalPages = Math.ceil(total / limit);
            var html = '<span>Total: ' + total + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page > 1 ? 'onclick="layui.orders.loadOrders(' + (page - 1) + ')"' : 'disabled') + '>Prev</button> ';
            html += '<span>Page ' + page + ' of ' + totalPages + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page < totalPages ? 'onclick="layui.orders.loadOrders(' + (page + 1) + ')"' : 'disabled') + '>Next</button>';
            $('#orders-pagination').html(html);
        },

        bindListEvents: function () {
            var that = this;
            $('#btn-search').on('click', function () { that.loadOrders(1); });
            $('#filter-state').on('change', function () { that.loadOrders(1); });
            $('#orders-tbody').on('click', '[data-action]', function () {
                that.showDetail($(this).attr('data-id'));
            });
        },

        showDetail: function (orderId) {
            var that = this;
            this.userInfo = common.getUser();
            common.request({
                url: '/orders/' + orderId, 
                success: function (res) {
                    if (res.success) {
                        that.currentOrder = res.data;
                        that.renderDetail(res.data);
                    }
                }
            });
            var $container = $('#app-content-inner');
            $container.find('.orders-list-view').hide();
            if (!$container.find('.order-detail-view').length) {
                $container.append($('.order-detail-view').show());
            } else {
                $container.find('.order-detail-view').show();
            }
            that.bindDetailEvents();
            that.loadHistory(orderId);
        },

        renderDetail: function (order) {
            $('#order-id').text(order.id);
            $('#order-state-badge').html(this.getStateBadge(order.state));
            this.renderProgress(order.state);
            this.renderActions(order.state);
        },

        loadHistory: function (orderId) {
            var that = this;
            common.request({
                url: '/orders/' + orderId + '/history',
                success: function (res) {
                    if (res.success) {
                        that.renderHistory(res.data);
                    }
                }
            });
        },

        renderHistory: function (history) {
            var html = '';
            for (var i = 0; i < history.length; i++) {
                var h = history[i];
                html += '<div style="padding:5px 0;border-bottom:1px solid #eee;">' +
                    h.from_state + ' → <strong>' + h.to_state + '</strong> ' +
                    '(' + common.formatDateTime(h.created_at) + ')' +
                    (h.notes ? ' - ' + h.notes : '') + '</div>';
            }
            $('#order-history').html(html || 'No history');
        },

        renderProgress: function (state) {
            var states = ['placed', 'pending_payment', 'paid', 'ticketing', 'ticketed', 'closed'];
            var idx = states.indexOf(state);
            var pct = ((idx + 1) / states.length) * 100;
            var $bar = $('#order-progress .layui-progress-bar');
            $bar.css('width', pct + '%');
            $bar.text(state);
            var colors = ['#1E9FFF', '#FFB800', '#5FB878', '#FF5722', '#2F4056', '#009688'];
            $bar.css('background-color', colors[Math.min(idx, colors.length - 1)]);
        },

        renderActions: function (state) {
            var $actions = $('#order-actions');
            $actions.hide().empty();
            var isAdmin = this.userInfo.role === 'administrator' || this.userInfo.role === 'operations_staff';

            if (!isAdmin) { $actions.show(); return; }

            $actions.show();
            if (state === 'placed') {
                $actions.append('<button class="layui-btn layui-btn-normal" data-action="initiate-payment">Initiate Payment</button> ');
                $actions.append('<button class="layui-btn layui-btn-danger" data-action="cancel">Cancel</button>');
            } else if (state === 'pending_payment') {
                $actions.append('<button class="layui-btn layui-btn-normal" data-action="confirm-payment">Confirm Payment</button>');
            } else if (state === 'paid') {
                $actions.append('<button class="layui-btn layui-btn-normal" data-action="start-ticketing">Start Ticketing</button>');
            } else if (state === 'ticketing') {
                $actions.append('<button class="layui-btn layui-btn-normal" data-action="ticket">Add Ticket #</button>');
            } else if (state === 'ticketed') {
                $actions.append('<button class="layui-btn layui-btn-normal" data-action="close">Close Order</button>');
            }
        },

        bindDetailEvents: function () {
            var that = this;
            $('[data-action="initiate-payment"]').on('click', function () {
                that.transition(that.currentOrder.id, 'initiate-payment');
            });
            $('[data-action="confirm-payment"]').on('click', function () {
                var method = prompt('Payment method:');
                if (method) {
                    common.request({
                        url: '/orders/' + that.currentOrder.id + '/confirm-payment',
                        method: 'POST',
                        data: { payment_method: method, amount: that.currentOrder.amount },
                        success: function () { that.showDetail(that.currentOrder.id); }
                    });
                }
            });
            $('[data-action="start-ticketing"]').on('click', function () {
                that.transition(that.currentOrder.id, 'start-ticketing');
            });
            $('[data-action="ticket"]').on('click', function () {
                var num = prompt('Ticket number:');
                if (num) {
                    common.request({
                        url: '/orders/' + that.currentOrder.id + '/ticket',
                        method: 'POST',
                        data: { ticket_number: num },
                        success: function () { that.showDetail(that.currentOrder.id); }
                    });
                }
            });
            $('[data-action="close"]').on('click', function () {
                that.transition(that.currentOrder.id, 'close');
            });
            $('[data-action="cancel"]').on('click', function () {
                that.transition(that.currentOrder.id, 'cancel');
            });
        },

        transition: function (id, action) {
            var that = this;
            layer.confirm('Confirm action: ' + action + '?', { icon: 3 }, function (idx) {
                common.request({
                    url: '/orders/' + id + '/' + action,
                    method: 'POST',
                    success: function (res) {
                        layer.msg('Done', { icon: 1 });
                        that.showDetail(id);
                    }
                });
                layer.close(idx);
            });
        },

        getStateBadge: function (state) {
            var colors = { placed: 'layui-bg-blue', pending_payment: 'layui-bg-orange', paid: 'layui-bg-green', 
                ticketing: 'layui-bg-purple', ticketed: 'layui-bg-black', canceled: 'layui-bg-red', closed: 'layui-bg-cyan' };
            var labels = { placed: 'Placed', pending_payment: 'Pending Payment', paid: 'Paid', 
                ticketing: 'Ticketing', ticketed: 'Ticketed', canceled: 'Canceled', closed: 'Closed' };
            return '<span class="layui-badge ' + (colors[state] || '') + '">' + (labels[state] || state) + '</span>';
        }
    };

    window.layui = window.layui || {};
    window.layui.orders = orders;

    exports('orders', orders);
});
