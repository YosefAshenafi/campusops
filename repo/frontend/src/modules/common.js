/**
 * CampusOps Common Utilities
 * Shared API helper, token management, and utility functions.
 */
layui.define(['jquery', 'layer'], function (exports) {
    var $ = layui.jquery;
    var layer = layui.layer;

    var common = {
        /**
         * Get the stored auth token
         */
        getToken: function () {
            return localStorage.getItem('campusops_token');
        },

        /**
         * Get the stored user info
         */
        getUser: function () {
            try {
                return JSON.parse(localStorage.getItem('campusops_user') || '{}');
            } catch (e) {
                return {};
            }
        },

        /**
         * Make an authenticated API request
         */
        request: function (options) {
            var token = this.getToken();
            var defaults = {
                contentType: 'application/json',
                dataType: 'json',
                headers: {
                    'Authorization': 'Bearer ' + token,
                    'X-Request-ID': this.uuid(),
                    'X-Timestamp': Math.floor(Date.now() / 1000).toString()
                },
                error: function (xhr) {
                    if (xhr.status === 401) {
                        localStorage.removeItem('campusops_token');
                        localStorage.removeItem('campusops_user');
                        window.location.href = '/login.html';
                        return;
                    }
                    var res = xhr.responseJSON || {};
                    layer.msg(res.error || 'Request failed', { icon: 2 });
                }
            };

            // Merge options
            var settings = $.extend(true, {}, defaults, options);
            settings.url = CampusOps.config.apiBase + (options.url || '');

            // Convert data to JSON string for POST/PUT
            if (settings.data && typeof settings.data === 'object'
                && settings.method && settings.method.toUpperCase() !== 'GET') {
                settings.data = JSON.stringify(settings.data);
            }

            return $.ajax(settings);
        },

        /**
         * Generate a UUID v4
         */
        uuid: function () {
            return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                var r = Math.random() * 16 | 0;
                var v = c === 'x' ? r : (r & 0x3 | 0x8);
                return v.toString(16);
            });
        },

        /**
         * Format a date string to MM/DD/YYYY hh:mm A
         */
        formatDateTime: function (dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            var year = d.getFullYear();
            var hours = d.getHours();
            var ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12 || 12;
            var minutes = String(d.getMinutes()).padStart(2, '0');
            var seconds = String(d.getSeconds()).padStart(2, '0');
            return month + '/' + day + '/' + year + ' ' + String(hours).padStart(2, '0') + ':' + minutes + ':' + seconds + ' ' + ampm;
        },

        /**
         * Format a date string to MM/DD/YYYY
         */
        formatDate: function (dateStr) {
            if (!dateStr) return '';
            var d = new Date(dateStr);
            var month = String(d.getMonth() + 1).padStart(2, '0');
            var day = String(d.getDate()).padStart(2, '0');
            var year = d.getFullYear();
            return month + '/' + day + '/' + year;
        }
    };

    exports('common', common);
});
