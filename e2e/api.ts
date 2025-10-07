export const BASE_URL = 'http://localhost:8888/wordpress';

export const ADMIN_USERNAME = 'admin';
export const ADMIN_PASSWORD = 'admin';

let cookies: Record<string, string> = {};

export function getUrl(uri: string) {
  return `${BASE_URL}${uri}`;
}
export async function request(input: RequestInfo | URL, init?: RequestInit) {
  const headers: Record<string, string> = {
    ...init?.headers as Record<string, string>,
  };
  headers['Cookie'] = Object.entries(cookies).map(([name, value]) => `${encodeURIComponent(name)}=${encodeURIComponent(value)}`).join('; ');
  const resp = await fetch(input, {
    ...init,
    headers,
  }); 
  if (resp.status >= 400) {
    throw new Error(`Server responded with ${resp.status}.`);
  }
  readCookies(resp);
  return resp;
}

export function readCookies(resp: Response) {
  // @ts-ignore
  const items = resp.headers.getSetCookie();
  for (const item of items) {
    const [ name, value ] = item.split(';')[0].split('=');
    cookies[decodeURIComponent(name)] = decodeURIComponent(value);
  }
}

export async function login(username: string, password: string) {
  await request(getUrl('/wp-login.php'), {
    body: new URLSearchParams({
      log: username,
      pwd: password,
    }),
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    method: 'POST',
  });
}

export async function getSettings() {
  const resp = await request(getUrl('/wp-admin/admin-ajax.php?action=altcha_get_settings'), {
  });
  return resp.json();
}

