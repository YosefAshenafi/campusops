/**
 * CampusOps Users Module
 * User CRUD operations and table rendering.
 */
layui.define(['jquery', 'layer', 'form', 'common'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;
       var form = layui.form;
    var common = layui.common;

    var users = {
        currentPage: 1,
        pageSize: 20,

        /**
         * Initialize the users list view.
         */
        initList: function () {
            this.loadUsers();
            this.bindListEvents();
        },

        /**
         * Load users with current filters.
         */
        loadUsers: function (page = 1) {
            var that = this;
            var params = {
                page: page,
                limit: that.pageSize,
                role: $('#filter-role').val() || '',
                status: $('#filter-status').val() || '',
                keyword: $('#filter-keyword').val() || ''
            };

            common.request({
                url: '/users',
                data: params,
                success: function (res) {
                    if (res.success) {
                        that.renderTable(res.data.list);
                        that.renderPagination(res.data.total, res.data.page, res.data.limit);
                    }
                }
            });
        },

        /**
         * Render the users table.
         */
        renderTable: function (list) {
            var $tbody = $('#users-tbody');
            $tbody.empty();

            if (!list || list.length === 0) {
                $tbody.append('<tr><td colspan="8" style="text-align: center; color: #999;">No users found</td></tr>');
                return;
            }

            for (var i = 0; i < list.length; i++) {
                var user = list[i];
                var lockedUntil = user.locked_until ? common.formatDateTime(user.locked_until) : '-';
                var failedAttempts = user.failed_attempts > 0 ? user.failed_attempts : '-';
                var statusBadge = user.status === 'active' 
                    ? '<span class="layui-badge layui-bg-green">Active</span>' 
                    : '<span class="layui-badge">Disabled</span>';
                var roleBadge = this.getRoleBadge(user.role);

                var row = '<tr>' +
                    '<td>' + user.id + '</td>' +
                    '<td>' + this.escapeHtml(user.username) + '</td>' +
                    '<td>' + roleBadge + '</td>' +
                    '<td>' + statusBadge + '</td>' +
                    '<td>' + failedAttempts + '</td>' +
                    '<td>' + lockedUntil + '</td>' +
                    '<td>' + common.formatDateTime(user.created_at) + '</td>' +
                    '<td>' +
                    '<button class="layui-btn layui-btn-xs" data-action="edit" data-id="' + user.id + '">Edit</button>' +
                    '<button class="layui-btn layui-btn-xs layui-btn-danger" data-action="delete" data-id="' + user.id + '">Delete</button>' +
                    '<button class="layui-btn layui-btn-xs layui-btn-warm" data-action="resetpwd" data-id="' + user.id + '">Reset Pwd</button>' +
                    '</td>' +
                    '</tr>';
                $tbody.append(row);
            }
        },

        /**
         * Render pagination.
         */
        renderPagination: function (total, page, limit) {
            var totalPages = Math.ceil(total / limit);
            var paginationHtml = '<span>Total: ' + total + '</span> ';
            paginationHtml += '<button class="layui-btn layui-xs" ' + (page > 1 ? 'onclick="layui.users.loadUsers(' + (page - 1) + ')"' : 'disabled') + '>Prev</button> ';
            paginationHtml += '<span>Page ' + page + ' of ' + totalPages + '</span> ';
            paginationHtml += '<button class="layui-btn layui-xs" ' + (page < totalPages ? 'onclick="layui.users.loadUsers(' + (page + 1) + ')"' : 'disabled') + '>Next</button>';
            $('#users-pagination').html(paginationHtml);
        },

        /**
         * Bind list view events.
         */
        bindListEvents: function () {
            var that = this;

            $('#btn-search').on('click', function () {
                that.loadUsers(1);
            });

            $('#filter-role, #filter-status').on('change', function () {
                that.loadUsers(1);
            });

            $('#btn-add-user').on('click', function () {
                that.showForm();
            });

            $('#users-tbody').on('click', '[data-action]', function () {
                var action = $(this).attr('data-action');
                var id = $(this).attr('data-id');
                if (action === 'edit') {
                    that.showForm(id);
                } else if (action === 'delete') {
                    that.deleteUser(id);
                } else if (action === 'resetpwd') {
                    that.resetPassword(id);
                }
            });

            form.render('select', 'user-filters');
        },

        /**
         * Show user form (create or edit).
         */
        showForm: function (userId) {
            var that = this;
            var isEdit = !!userId;

            var $container = $('#app-content-inner');
            $container.find('.users-list-view').hide();

            function onFormReady() {
                $('#form-title').text(isEdit ? 'Edit User' : 'Add User');
                $('#btn-submit').text(isEdit ? 'Update User' : 'Create User');
                $('#password-group').hide();
                $('#generate-password').prop('checked', false);

                if (isEdit) {
                    $('#password-group').show();
                    common.request({
                        url: '/users/' + userId,
                        success: function (res) {
                            if (res.success) {
                                that.fillForm(res.data);
                            }
                        }
                    });
                } else {
                    that.resetForm();
                }

                $container.find('.user-form-view').show();
                form.render();
                that.bindFormEvents();
            }

            if (!$container.find('.user-form-view').length) {
                $('<div>').appendTo($container).load('/src/views/users/form.html', onFormReady);
            } else {
                onFormReady();
            }
        },

        /**
         * Fill form with user data.
         */
        fillForm: function (user) {
            $('#user-id').val(user.id);
            $('#input-username').val(user.username);
            $('#input-role').val(user.role);
            $('#input-status').val(user.status);
            $('#password-group').hide();
            // Re-render selects so Layui custom dropdowns reflect the new values
            form.render('select');
        },

        /**
         * Reset form.
         */
        resetForm: function () {
            $('#user-id').val('');
            $('#user-form')[0].reset();
            $('#password-group').show();
        },

        /**
         * Bind form events.
         */
        bindFormEvents: function () {
            var that = this;

            form.on('submit(submit-user)', function (data) {
                that.saveUser(data.field);
                return false;
            });

            $('#btn-cancel').off('click').on('click', function () {
                $('#app-content-inner').find('.user-form-view').hide();
                $('#app-content-inner').find('.users-list-view').show();
            });

            $('#generate-password').off('change').on('change', function () {
                if ($(this).prop('checked')) {
                    $('#input-password').val(that.generatePassword()).attr('readonly', true);
                } else {
                    $('#input-password').val('').attr('readonly', false);
                }
            });
        },

        /**
         * Save user (create or update).
         */
        saveUser: function (data) {
            var that = this;
            var isEdit = !!data.id;
            var url = '/users' + (isEdit ? '/' + data.id : '');
            var method = isEdit ? 'PUT' : 'POST';

            common.request({
                url: url,
                method: method,
                data: data,
                success: function (res) {
                    layer.msg(res.message || 'User saved', { icon: 1 });
                    if (!isEdit && res.data && res.data.temp_password) {
                        that.showTempPassword(res.data);
                    }
                    $('#app-content-inner').find('.user-form-view').hide();
                    $('#app-content-inner').find('.users-list-view').show();
                    that.loadUsers(1);
                }
            });
        },

        /**
         * Delete user.
         */
        deleteUser: function (id) {
            var that = this;
            layer.confirm('Are you sure you want to disable this user?', { icon: 3, title: 'Confirm', btn: ['OK', 'Cancel'] }, function (index) {
                common.request({
                    url: '/users/' + id,
                    method: 'DELETE',
                    success: function (res) {
                        layer.msg('User disabled', { icon: 1 });
                        that.loadUsers(that.currentPage);
                    }
                });
                layer.close(index);
            });
        },

        /**
         * Reset user password.
         */
        resetPassword: function (id) {
            var that = this;
            common.request({
                url: '/users/' + id + '/password',
                method: 'PUT',
                success: function (res) {
                    if (res.success && res.data && res.data.temp_password) {
                        that.showTempPassword(res.data);
                    }
                }
            });
        },

        /**
         * Show temporary password modal.
         */
        showTempPassword: function (data) {
            $('#temp-password-user').text($('#input-username').val() || 'User #' + data.user_id);
            $('#temp-password-value').text(data.temp_password);
            layer.open({
                type: 1,
                title: 'Password Reset',
                content: $('#temp-password-modal'),
                btn: 'Done',
                area: ['400px', '250px'],
                yes: function (index) {
                    layer.close(index);
                }
            });
        },

        /**
         * Generate random password.
         */
        generatePassword: function () {
            var arr = new Uint8Array(8);
            crypto.getRandomValues(arr);
            return Array.from(arr, function(b) { return b.toString(16).padStart(2, '0'); }).join('');
        },

        /**
         * Get role badge HTML.
         */
        getRoleBadge: function (role) {
            var colors = {
                administrator: 'layui-bg-red',
                operations_staff: 'layui-bg-orange',
                team_lead: 'layui-bg-blue',
                reviewer: 'layui-bg-purple',
                regular_user: 'layui-bg-gray'
            };
            var labels = {
                administrator: 'Admin',
                operations_staff: 'Ops Staff',
                team_lead: 'Team Lead',
                reviewer: 'Reviewer',
                regular_user: 'User'
            };
            var color = colors[role] || 'layui-bg-gray';
            var label = labels[role] || role;
            return '<span class="layui-badge ' + color + '">' + label + '</span>';
        },

        /**
         * Escape HTML.
         */
        escapeHtml: function (text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // Expose to global for onclick handlers
    window.layui = window.layui || {};
    window.layui.users = users;

    exports('users', users);
});