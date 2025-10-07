import { Selector } from "testcafe";

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
  await delay(250);
  await t.typeText('#user_login', username);
  await t.typeText('#user_pass', password);
  await t.click(Selector('#wp-submit'));
  await delay(3000);
}

export async function getSettings(t: TestController): Promise<{
  defaults: Record<string, any>;
  settings: Record<string, any>;
}> {
  const resp = await t.request(getUrl('/wp-admin/admin-ajax.php?action=altcha_get_settings'), {
    withCredentials: true,
  });
  const status = await resp.status;
  if (status >= 400) {
    throw new Error(`Server responded with ${status}.`);
  }
  const body = await resp.body as any;
  return body?.data;
}

export async function setSettings(t: TestController, data: object): Promise<object> {
  const resp = await t.request(getUrl('/wp-admin/admin-ajax.php'), {
    body: new URLSearchParams({
      action: 'altcha_set_settings',
      data: JSON.stringify(data),
    }),
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    method: 'POST',
    withCredentials: true,
  });
  const status = await resp.status;
  if (status >= 400) {
    throw new Error(`Server responded with ${status}.`);
  }
  return resp.body as object;
}

export async function setUnderAttack(t: TestController, level: number): Promise<object> {
  const resp = await t.request(getUrl('/wp-admin/admin-ajax.php'), {
    body: new URLSearchParams({
      action: 'altcha_set_under_attack',
      under_attack: String(level),
    }),
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    method: 'POST',
    withCredentials: true,
  });
  const status = await resp.status;
  if (status >= 400) {
    throw new Error(`Server responded with ${status}.`);
  }
  return resp.body as object;
}

export async function bootstrap(t: TestController, options: {
  settings: any;
  underAttack: number;
}) {
  await login(t);
  const { defaults } = await getSettings(t);
  await setSettings(t, {
    ...defaults,
    ...options.settings,
  });
  await setUnderAttack(t, options.underAttack);
  await t.deleteCookies();
}