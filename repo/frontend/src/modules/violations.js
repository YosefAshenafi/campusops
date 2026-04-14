/**
 * CampusOps Violations Module
 * Violation rules, violations, appeals, and point aggregation.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
    var common = layui.common;

    var violations = {
        userInfo: null,

        initList: function () {
            this.userInfo = common.getUser();
            this.loadViolations(1);
            this.bindEvents();
        },

        init: function () {
            this.initList();
        },

        loadRules: function () {
            var that = this;
            common.request({
                url: '/violations/rules',
                success: function (res) {
                    if (res.success) {
                        that.renderRules(res.data);
                    }
                }
            });
        },

        renderRules: function (list) {
            var $tbody = $('#rules-tbody');
            $tbody.empty();
            for (var i = 0; i < list.length; i++) {
                var r = list[i];
                $tbody.append('<tr>' +
                    '<td>' + r.id + '</td>' +
                    '<td>' + r.name + '</td>' +
                    '<td>' + r.category + '</td>' +
                    '<td>' + (r.points > 0 ? '+' : '') + r.points + '</td>' +
                    '<td>' + (r.description || '-') + '</td>' +
                    '<td><button class="layui-btn layui-btn-xs" data-action="edit" data-id="' + r.id + '">Edit</button> ' +
                    '<button class="layui-btn layui-btn-xs layui-btn-danger" data-action="delete" data-id="' + r.id + '">Delete</button></td>' +
                    '</tr>');
            }
        },

        loadViolations: function (page, userId) {
            var that = this;
            var params = { page: page || 1, limit: 20 };
            if (userId) params.user_id = userId;

            common.request({
                url: '/violations',
                data: params,
                success: function (res) {
                    if (res.success) {
                        that.renderViolations(res.data.list);
                        that.renderPagination(res.data.total, res.data.page, res.data.limit);
                    }
                }
            });
        },

        renderViolations: function (list) {
            var that = this;
            var $tbody = $('#violations-tbody');
            $tbody.empty();
            if (!list || list.length === 0) {
                $tbody.append('<tr><td colspan="7" style="text-align:center;color:#999;">No violations</td></tr>');
                return;
            }
            for (var i = 0; i < list.length; i++) {
                var v = list[i];
                var statusBadge = that.getStatusBadge(v.status);
                var pointClass = v.points > 0 ? 'layui-bg-red' : 'layui-bg-green';
                $tbody.append('<tr>' +
                    '<td>' + v.id + '</td>' +
                    '<td>' + v.username + '</td>' +
                    '<td>' + v.rule_name + '</td>' +
                    '<td><span class="layui-badge ' + pointClass + '">' + (v.points > 0 ? '+' : '') + v.points + '</span></td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + common.formatDateTime(v.created_at) + '</td>' +
                    '<td><button class="layui-btn layui-btn-xs" data-action="view" data-id="' + v.id + '">View</button></td>' +
                    '</tr>');
            }
        },

        renderPagination: function (total, page, limit) {
            var totalPages = Math.ceil(total / limit);
            var html = '<span>Total: ' + total + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page > 1 ? 'onclick="layui.violations.loadViolations(' + (page - 1) + ')"' : 'disabled') + '>Prev</button> ';
            html += '<span>Page ' + page + ' of ' + totalPages + '</span> ';
            html += '<button class="layui-btn layui-xs" ' + (page < totalPages ? 'onclick="layui.violations.loadViolations(' + (page + 1) + ')"' : 'disabled') + '>Next</button>';
            $('#violations-pagination').html(html);
        },

        getStatusBadge: function (status) {
            var colors = { pending: 'layui-bg-orange', approved: 'layui-bg-red', rejected: 'layui-bg-green', 
                under_review: 'layui-bg-blue', resolved: 'layui-bg-cyan' };
            var labels = { pending: 'Pending', approved: 'Approved', rejected: 'Rejected', 
                under_review: 'Under Review', resolved: 'Resolved' };
            return '<span class="layui-badge ' + (colors[status] || '') + '">' + (labels[status] || status) + '</span>';
        },

        getUserPoints: function (userId, callback) {
            common.request({
                url: '/violations/user/' + userId,
                success: function (res) {
                    if (res.success && callback) callback(res.data);
                }
            });
        },

        getGroupPoints: function (groupId, callback) {
            common.request({
                url: '/violations/group/' + groupId,
                success: function (res) {
                    if (res.success && callback) callback(res.data);
                }
            });
        },

        submitAppeal: function (violationId, notes, callback) {
            common.request({
                url: '/violations/' + violationId + '/appeal',
                method: 'POST',
                data: { notes: notes },
                success: function (res) {
                    if (callback) callback(res);
                }
            });
        },

        reviewViolation: function (violationId, decision, notes, callback) {
            common.request({
                url: '/violations/' + violationId + '/review',
                method: 'POST',
                data: { decision: decision, notes: notes },
                success: function (res) {
                    if (callback) callback(res);
                }
            });
        },

        finalDecision: function (violationId, uphold, notes, callback) {
            common.request({
                url: '/violations/' + violationId + '/final-decision',
                method: 'POST',
                data: { uphold: uphold, notes: notes },
                success: function (res) {
                    if (callback) callback(res);
                }
            });
        },

        bindEvents: function () {
            var that = this;

            $('#btn-add-rule').on('click', function () {
                that.showRuleForm();
            });

            $('#btn-search').on('click', function () {
                that.loadViolations(1, $('#filter-user-id').val());
            });

            $('#rules-tbody').on('click', '[data-action]', function () {
                var action = $(this).attr('data-action');
                var id = $(this).attr('data-id');
                if (action === 'edit') that.showRuleForm(id);
                else if (action === 'delete') that.deleteRule(id);
            });

            $('#violations-tbody').on('click', '[data-action="view"]', function () {
                that.showViolationDetail($(this).attr('data-id'));
            });
        },

        showRuleForm: function (ruleId) {
            var name = ruleId ? 'Edit Rule' : 'Add Rule';
            var content = '<form class="layui-form layui-form-pane">' +
                '<div class="layui-form-item"><label class="layui-form-label">Name</label><div class="layui-input-block"><input type="text" name="name" class="layui-input" required></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Category</label><div class="layui-input-block"><input type="text" name="category" class="layui-input"></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Points</label><div class="layui-input-block"><input type="number" name="points" class="layui-input" required></div></div>' +
                '<div class="layui-form-item"><label class="layui-form-label">Description</label><div class="layui-input-block"><textarea name="description" class="layui-textarea"></textarea></div></div>' +
                '<div class="layui-form-item"><button class="layui-btn" lay-submit>Save</button></div></form>';

            layer.open({ type: 1, title: name, content: content, area: ['500px', '400px'] });
        },

        showViolationDetail: function (id) {
            var that = this;
            common.request({
                url: '/violations/' + id,
                success: function (res) {
                    if (res.success) {
                        var v = res.data;
                        var html = '<div style="padding:20px;"><table class="layui-table"><tr><td>User:</td><td>' + v.username + '</td></tr>' +
                            '<tr><td>Rule:</td><td>' + v.rule_name + '</td></tr>' +
                            '<tr><td>Points:</td><td>' + v.points + '</td></tr>' +
                            '<tr><td>Status:</td><td>' + v.status + '</td></tr>' +
                            '<tr><td>Notes:</td><td>' + (v.notes || '-') + '</td></tr>' +
                            '<tr><td>Created:</td><td>' + common.formatDateTime(v.created_at) + '</td></tr></table>';

                        if (v.status === 'pending' && that.userInfo.id === v.user_id) {
                            html += '<button class="layui-btn" onclick="layui.violations.promptAppeal(' + v.id + ')">Appeal</button>';
                        }
                        if (that.userInfo.role === 'reviewer' || that.userInfo.role === 'administrator') {
                            html += '<button class="layui-btn layui-btn-warm" onclick="layui.violations.promptReview(' + v.id + ')">Review</button>';
                        }
                        html += '</div>';
                        layer.open({ type: 1, title: 'Violation #' + id, content: html, area: ['400px', '400px'] });
                    }
                }
            });
        },

        promptAppeal: function (id) {
            var notes = prompt('Appeal notes:');
            if (notes) {
                this.submitAppeal(id, notes, function (res) {
                    layer.msg('Appeal submitted', { icon: 1 });
                });
            }
        },

        promptReview: function (id) {
            var decision = prompt('Decision (uphold/reject):');
            var notes = prompt('Review notes:');
            if (decision) {
                this.finalDecision(id, decision === 'uphold', notes, function (res) {
                    layer.msg('Decision recorded', { icon: 1 });
                });
            }
        },

        deleteRule: function (id) {
            layer.confirm('Delete this rule?', { icon: 3 }, function (idx) {
                common.request({
                    url: '/violations/rules/' + id,
                    method: 'DELETE',
                    success: function () {
                        layer.msg('Rule deleted', { icon: 1 });
                    }
                });
                layer.close(idx);
            });
        }
    };

    var that = violations;

    window.layui = window.layui || {};
    window.layui.violations = violations;

    exports('violations', violations);
});