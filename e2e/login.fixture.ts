import { Selector } from 'testcafe';
import { ADMIN_PASSWORD, ADMIN_USERNAME, bootstrap, delay, getUrl, loginAjax } from './helpers';

fixture`Interceptor - Login`
  .page`${getUrl('/')}`;

test('Should not protect the login page when disabled', async (t) => {
  await bootstrap(t, {
    settings: {
      protectLogin: false,
    },
    underAttack: 0,
  });
  await t.navigateTo(getUrl('/wp-login.php'));
  await delay(250);
  await t.typeText('#user_login', ADMIN_USERNAME);
  await t.typeText('#user_pass', ADMIN_PASSWORD);
  await t.click(Selector('#wp-submit'));
  await t.expect(Selector('altcha-widget').exists).notOk();
});


test('Should protect the login page when enabled', async (t) => {
  await bootstrap(t, {
    settings: {
      protectLogin: true,
    },
    underAttack: 0,
  });
  await t.navigateTo(getUrl('/wp-login.php'));
  await delay(250);
  await t.typeText('#user_login', ADMIN_USERNAME);
  await t.typeText('#user_pass', ADMIN_PASSWORD);
  await t.click(Selector('#wp-submit'));
  await t.expect(Selector('altcha-widget').exists).ok();
});

test('Should be able to log-in without verification when disabled', async (t) => {
  await bootstrap(t, {
    settings: {
      protectLogin: false,
    },
    underAttack: 0,
  });
  await t.deleteCookies();
  await loginAjax(t);
  await t.expect(await loginAjax(t)).eql(true);
});


test('Should not be able to log-in without verification when enabled', async (t) => {
  await bootstrap(t, {
    settings: {
      protectLogin: true,
    },
    underAttack: 0,
  });
  await t.deleteCookies();
  let errored = false;
  try {
    await loginAjax(t);
  } catch (err: any) {
    await t.expect(err.message).contains('responded with 403');
    errored = true;
  }
  await t.expect(errored).eql(true);
});
