import { Selector } from 'testcafe';
import { bootstrap, delay, getUrl } from './helpers';

fixture`Under Attack`
  .page`${getUrl('/')}`;

test('Should display target page when disabled', async (t) => {
  await bootstrap(t, {
    settings: {},
    underAttack: 0,
  });
  await t.eval(() => location.reload());
  await t.expect(Selector('#altcha-under-attack').exists).notOk();
});

test('Should display challenge page when enabled', async (t) => {
  await bootstrap(t, {
    settings: {},
    underAttack: 1,
  });
  await t.eval(() => location.reload());
  await t.expect(Selector('#altcha-under-attack').exists).ok();
});

test('Should auto verify, reload and display target page when enabled', async (t) => {
  await bootstrap(t, {
    settings: {},
    underAttack: 1,
  });
  await t.eval(() => location.reload());
  await t.expect(Selector('#altcha-under-attack').exists).ok();
  await delay(5000);
  await t.expect(Selector('#altcha-under-attack').exists).notOk();
});


