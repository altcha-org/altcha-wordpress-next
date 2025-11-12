/**
 * Copyright (c) 2025 BAU Software s.r.o., Czechia. All rights reserved.
 *
 * This file is part of the Software licensed under the
 * END-USER LICENSE AGREEMENT (EULA)
 *
 * License Summary:
 * - Source is available for review, testing, debugging, and evaluation.
 * - Distribution of the Software or source code is prohibited.
 * - Modifications are allowed only for internal testing/debugging,
 *   not for production or deployment.
 *
 * The full license text can be found in the LICENSE file
 * distributed with this source code.
 *
 * Unauthorized distribution, modification, or production use of
 * this Software is strictly prohibited.
 */

(() => {
  const config = pluginData?.altcha || {};

  async function callAction(action, options = {}) {
    const data = new FormData();
    data.append('action', action);
    for (const option in options) {
      data.append(option, options[option]);
    }
    const resp = await fetch(ajaxurl, {
      body: data,
      method: 'POST',
    });
    const json = await resp.json();
    return json.data;
  }

  const widgetEl = document.getElementById("altcha-dashboard-widget");
  const appEl = document.getElementById("altcha-app");
  const api = {
    getAnalyticsData: async (options) => {
      const { actions, enabled, events, interval, plugins, time_end, time_start, tz_offset } = await callAction('altcha_get_analytics_data', options);
      if (enabled === false) {
        return null;
      }
      return {
        actions: actions.map(({ count, event, action }) => ({
          action,
          count: +count,
          event,
        })),
        events: events.map(({ count, event, time }) => ({
          count: +count,
          event,
          time: +time * 1_000, // ms
        })),
        interval: +interval * 1_000, // ms
        plugins: plugins.map(({ count, plugin }) => {
          return {
            count: +count,
            plugin,
          };
        }),
        timeEnd: +time_end * 1_000, // ms
        timeStart: +time_start * 1_000, // ms
        tzOffset: +tz_offset * 1_000, // ms
      };
    },
    getEvents: async (options) => {
      return callAction('altcha_get_events', options);
    },
    getSettings: async () => {
      const resp = await fetch(ajaxurl + '?action=altcha_get_settings');
      const json = await resp.json();
      return json.data;
    },
    setSettings: async (data) => {
      const body = new FormData();
      body.append('action', 'altcha_set_settings');
      body.append('data', JSON.stringify(data));
      const resp = await fetch(ajaxurl, {
        body,
        method: 'POST',
      });
      return resp.status < 400;
    },
    setLicense: async (license) => {
      const body = new FormData();
      body.append('action', 'altcha_set_license');
      if (license) {
        body.append('license', license);
      }
      const resp = await fetch(ajaxurl, {
        body,
        method: 'POST',
      });
      return resp.status < 400;
    },
    setUnderAttack: async (level) => {
      const body = new FormData();
      body.append('action', 'altcha_set_under_attack');
      if (level) {
        body.append('under_attack', level);
      }
      const resp = await fetch(ajaxurl, {
        body,
        method: 'POST',
      });
      return resp.status < 400;
    }
  };

  if (widgetEl && window.altchaMountDashboardWidget) {
    window.altchaMountDashboardWidget(widgetEl, {
      api,
      analyticsLink: config?.pluginUrl + '#?route=analytics',
      settingsLink: config?.pluginUrl + '#?route=settings',
      firewallLink: config?.pluginUrl + '#?route=firewall',
      startLink: config?.pluginUrl + '#?route=start',
      variant: 'wp',
      underAttack: parseInt(config?.underAttack || '0', 10),
    });
  }

  if (appEl && window.altchaMountApp) {
    window.altchaMountApp(appEl, {
      api,
      eventsEnabled: config?.eventsEnabled === true || config?.eventsEnabled === '1',
      installedPlugins: config?.installedPlugins,
      license: config?.license,
      variant: 'wp',
      underAttack: parseInt(config?.underAttack || '0', 10),
    });
  }
})();
