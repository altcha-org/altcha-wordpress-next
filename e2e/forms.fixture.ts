import { Selector } from 'testcafe';
import {  adminAjax, bootstrap, delay, getUrl } from './helpers';

const TEST_FORM_URI = '/index.php/test-form/';

fixture`Interceptor - Forms`
  .page`${getUrl('/')}`;

test('Should perform verification on submit', async (t) => {
  await bootstrap(t, {
    settings: {},
    underAttack: 0,
  });
  await t.navigateTo(getUrl(TEST_FORM_URI));
  await delay(250);
  await t.click(Selector('[type="submit"]'));
  await t.expect(Selector('altcha-widget').exists).ok();
});

test('Should not be able to POST to admin-ajax.php without a cookie', async (t) => {
  await bootstrap(t, {
    settings: {},
    underAttack: 0,
  });
  await t.deleteCookies();
  let errored = false;
  try {
    await adminAjax(t, new URLSearchParams({
      action: 'test',
    }));
  } catch (err: any) {
    await t.expect(err.message).contains('responded with 403');
    errored = true;
  }
});

test('Should be able to GET from admin-ajax.php without a cookie', async (t) => {
  await bootstrap(t, {
    settings: {},
    underAttack: 0,
  });
  await t.deleteCookies();
  const resp = await t.request(getUrl('/wp-admin/admin-ajax.php?action=test'));
  await t.expect(await resp.status).eql(200);
});


