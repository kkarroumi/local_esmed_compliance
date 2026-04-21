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
 * Heartbeat AMD module.
 *
 * Sends a keep-alive beacon at a configurable interval while the tab is
 * visible and posts an explicit close on tab unload via navigator.sendBeacon.
 *
 * @module     local_esmed_compliance/heartbeat
 * @copyright  2026 ESMED
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log'], function(Log) {
    'use strict';

    var Heartbeat = {
        config: null,
        timerId: null,

        init: function(config) {
            if (!config || !config.endpoint || !config.sesskey) {
                Log.debug('local_esmed_compliance/heartbeat: missing config, aborting.');
                return;
            }
            Heartbeat.config = config;
            Heartbeat.schedule();
            document.addEventListener('visibilitychange', Heartbeat.onVisibilityChange);
            window.addEventListener('beforeunload', Heartbeat.onBeforeUnload);
            window.addEventListener('pagehide', Heartbeat.onBeforeUnload);
        },

        schedule: function() {
            Heartbeat.clear();
            var seconds = parseInt(Heartbeat.config.interval, 10);
            if (!seconds || seconds < 15) {
                seconds = 30;
            }
            Heartbeat.timerId = window.setInterval(Heartbeat.beat, seconds * 1000);
        },

        clear: function() {
            if (Heartbeat.timerId) {
                window.clearInterval(Heartbeat.timerId);
                Heartbeat.timerId = null;
            }
        },

        beat: function() {
            if (document.visibilityState !== 'visible') {
                return;
            }
            var body = new URLSearchParams();
            body.append('sesskey', Heartbeat.config.sesskey);
            body.append('action', 'heartbeat');
            window.fetch(Heartbeat.config.endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: body.toString(),
                keepalive: true
            }).catch(function(err) {
                Log.debug('local_esmed_compliance/heartbeat: beat failed', err);
            });
        },

        onVisibilityChange: function() {
            if (document.visibilityState === 'visible') {
                Heartbeat.beat();
            }
        },

        onBeforeUnload: function() {
            Heartbeat.clear();
            if (!navigator.sendBeacon || !Heartbeat.config) {
                return;
            }
            var body = new URLSearchParams();
            body.append('sesskey', Heartbeat.config.sesskey);
            body.append('action', 'close');
            body.append('closure_type', 'beacon');
            var blob = new Blob([body.toString()], {
                type: 'application/x-www-form-urlencoded'
            });
            navigator.sendBeacon(Heartbeat.config.endpoint, blob);
        }
    };

    return {
        init: Heartbeat.init
    };
});
