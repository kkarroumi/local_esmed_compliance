// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Dashboard AMD module.
 *
 * Polls the metrics endpoint at a fixed interval and updates every
 * element with a matching `data-field` attribute in place, so the
 * page feels live without a full re-render. Also delegates clicks on
 * per-row acknowledge buttons to the acknowledge_alert webservice.
 *
 * @module     local_esmed_compliance/dashboard
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log', 'core/ajax'], function(Log, Ajax) {
    'use strict';

    var Dashboard = {
        config: null,
        timerId: null,

        init: function(config) {
            if (!config || !config.endpoint || !config.sesskey) {
                Log.debug('local_esmed_compliance/dashboard: missing config, aborting.');
                return;
            }
            Dashboard.config = config;
            Dashboard.schedule();
            document.addEventListener('visibilitychange', Dashboard.onVisibilityChange);
            Dashboard.bindAckHandler();
        },

        schedule: function() {
            Dashboard.clear();
            var seconds = parseInt(Dashboard.config.interval, 10);
            if (!seconds || seconds < 5) {
                seconds = 30;
            }
            Dashboard.timerId = window.setInterval(Dashboard.refresh, seconds * 1000);
        },

        clear: function() {
            if (Dashboard.timerId) {
                window.clearInterval(Dashboard.timerId);
                Dashboard.timerId = null;
            }
        },

        refresh: function() {
            if (document.visibilityState !== 'visible') {
                return;
            }
            var url = Dashboard.config.endpoint + '?sesskey=' + encodeURIComponent(Dashboard.config.sesskey);
            window.fetch(url, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'}
            }).then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            }).then(Dashboard.applyMetrics)
              .catch(function(err) {
                  Log.debug('local_esmed_compliance/dashboard: refresh failed', err);
              });
        },

        applyMetrics: function(context) {
            var root = document.getElementById('esmed-compliance-dashboard');
            if (!root) {
                return;
            }
            var fields = root.querySelectorAll('[data-field]');
            fields.forEach(function(node) {
                var path = node.getAttribute('data-field').split('.');
                var value = context;
                for (var i = 0; i < path.length; i++) {
                    if (value === null || typeof value !== 'object' || !(path[i] in value)) {
                        value = null;
                        break;
                    }
                    value = value[path[i]];
                }
                if (value !== null && value !== undefined && typeof value !== 'object') {
                    node.textContent = String(value);
                }
            });
        },

        onVisibilityChange: function() {
            if (document.visibilityState === 'visible') {
                Dashboard.refresh();
            }
        },

        bindAckHandler: function() {
            var root = document.getElementById('esmed-compliance-dashboard');
            if (!root) {
                return;
            }
            root.addEventListener('click', function(event) {
                var target = event.target;
                if (!target || target.getAttribute('data-action') !== 'ack') {
                    return;
                }
                event.preventDefault();
                Dashboard.acknowledgeAlert(target);
            });
        },

        acknowledgeAlert: function(button) {
            var alertid = parseInt(button.getAttribute('data-alertid'), 10);
            if (!alertid) {
                return;
            }
            button.disabled = true;
            Ajax.call([{
                methodname: 'local_esmed_compliance_acknowledge_alert',
                args: {alertid: alertid}
            }])[0].then(function(response) {
                if (response && response.acknowledged) {
                    var row = button.closest('tr');
                    if (row && row.parentNode) {
                        row.parentNode.removeChild(row);
                    }
                    Dashboard.refresh();
                } else {
                    button.disabled = false;
                }
                return response;
            }).catch(function(err) {
                Log.debug('local_esmed_compliance/dashboard: acknowledge failed', err);
                button.disabled = false;
            });
        }
    };

    return {
        init: Dashboard.init
    };
});
