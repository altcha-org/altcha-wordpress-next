import { ClientFunction, Selector } from "testcafe";

export const BASE_URL = 'http://localhost:8888/wordpress';

export const ADMIN_USERNAME = 'admin';
export const ADMIN_PASSWORD = 'admin';

export async function delay(ms: number) {
  await new Promise((resolve) => setTimeout(resolve, ms));
}

export function getUrl(uri: string) {
  return `${BASE_URL}${uri}`;
}

export async function adminAjax(t: TestController, body: URLSearchParams) {
  const resp = await t.request(getUrl('/wp-admin/admin-ajax.php'), {
    body,
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    method: 'POST',
  });
  const status = await resp.status;
  if (status >= 400) {
    console.error(await resp.body)
    throw new Error(`Server responded with ${status}.`);
  }
  return true;
}

export async function loginAjax(t: TestController, username: string = ADMIN_USERNAME, password: string = ADMIN_PASSWORD) {
  const resp = await t.request(getUrl('/wp-login.php'), {
    body: new URLSearchParams({
      log: username,
      pwd: password,
    }),
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    method: 'POST',
  });
  const status = await resp.status;
  if (status >= 400) {
    throw new Error(`Server responded with ${status}.`);
  }
  return true;
}

export async function login(t: TestController, username: string = ADMIN_USERNAME, password: string = ADMIN_PASSWORD) {
  await t.navigateTo(getUrl('/wp-login.php'));
  await delay(1000);
  await t.typeText('#user_login', username, {
    speed: 0.1
  });
  await t.typeText('#user_pass', password, {
    speed: 0.1
  });
  await t.click(Selector('#wp-submit'));
  await delay(4000);
}

const getPluginDataNonce = ClientFunction(() => (window as any).pluginData?.altcha?.nonce);

export async function getAdminNonce(t: TestController): Promise<string> {
  await t.navigateTo(getUrl('/wp-admin/admin.php?page=altcha'));
  await delay(1500);
  const nonce = await getPluginDataNonce();
  if (!nonce) {
    throw new Error('Could not read admin ajax nonce from pluginData.');
  }
  return nonce;
}

// admin-ajax.php requires the WordPress auth cookie (httpOnly), which TestCafe's
// t.request() does not reliably forward. Real in-page fetch() carries it correctly
// (same mechanism admin-wp.js itself uses), so ajax calls run in the browser context.
const browserAjax = ClientFunction((params: Record<string, string>, method: string) => {
  const w = window as any;
  const query = new URLSearchParams(params).toString();
  const url = method === 'GET' ? `${w.ajaxurl}?${query}` : w.ajaxurl;
  const options: RequestInit = { method };
  if (method === 'POST') {
    options.body = new URLSearchParams(params);
  }
  return fetch(url, options).then((r) => r.text().then((text) => ({ status: r.status, text })));
});

async function ajaxWithNonce(params: Record<string, string>, method: 'GET' | 'POST' = 'POST'): Promise<any> {
  const { status, text } = await browserAjax(params, method);
  if (status >= 400) {
    throw new Error(`Server responded with ${status}.`);
  }
  return JSON.parse(text);
}

export async function getSettings(t: TestController, nonce: string): Promise<{
  defaults: Record<string, any>;
  settings: Record<string, any>;
}> {
  const body = await ajaxWithNonce({
    action: 'altcha_get_settings',
    nonce,
  }, 'GET');
  return body?.data;
}

export async function setSettings(t: TestController, data: object, nonce: string): Promise<object> {
  return ajaxWithNonce({
    action: 'altcha_set_settings',
    data: JSON.stringify(data),
    nonce,
  });
}

export async function setUnderAttack(t: TestController, level: number, nonce: string): Promise<object> {
  return ajaxWithNonce({
    action: 'altcha_set_under_attack',
    under_attack: String(level),
    nonce,
  });
}

export async function bootstrap(t: TestController, options: {
  settings: any;
  underAttack: number;
}) {
  await login(t);
  const nonce = await getAdminNonce(t);
  const { defaults } = await getSettings(t, nonce);
  await setSettings(t, {
    ...defaults,
    ...options.settings,
  }, nonce);
  await setUnderAttack(t, options.underAttack, nonce);
  await t.deleteCookies();
}