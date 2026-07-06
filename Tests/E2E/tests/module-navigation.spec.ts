import { test, expect, type Page, type FrameLocator } from '@playwright/test';

const t3Major = Number(process.env.T3_MAJOR ?? '13');

const SUBMODULE_LABELS = ['Providers', 'Authorizers', 'Reporters'];
// v14 core labels the showSubmoduleOverview entry itself; v13 uses the
// extension's own label for the manually built menu.
const OVERVIEW_LABEL = t3Major >= 14 ? 'Module overview' : 'Overview';
const SWITCHER_LABELS = [OVERVIEW_LABEL, ...SUBMODULE_LABELS];

/**
 * Backend module content is rendered inside the content iframe on both
 * supported majors.
 */
const contentFrame = (page: Page): FrameLocator =>
  page.frameLocator('#typo3-contentIframe');

const openMonitoringModule = async (page: Page): Promise<FrameLocator> => {
  await page.goto('/typo3');
  await page.locator('[data-modulemenu-identifier="monitoring"]').click();
  return contentFrame(page);
};

/**
 * The docheader submodule switcher differs per major: v13 renders a
 * MenuRegistry select box, v14 a DropDownButton in the button bar.
 */
/**
 * Opens the v14 docheader dropdown, a native popover toggled through the
 * button's popovertarget attribute. Retry the click in case it raced the
 * frame still loading.
 */
const openV14Dropdown = async (frame: FrameLocator) => {
  const dropdownButton = frame.getByRole('button', { name: /^Module action/ });
  await expect(dropdownButton).toBeVisible();
  const popoverId = await dropdownButton.getAttribute('popovertarget');
  const menu = frame.locator(popoverId !== null ? `[id="${popoverId}"]` : '.dropdown-menu');
  await expect(async () => {
    if (!(await menu.isVisible())) {
      await dropdownButton.click();
    }
    await expect(menu).toBeVisible({ timeout: 1_000 });
  }).toPass();
  return menu;
};

const expectSwitcherEntries = async (page: Page, frame: FrameLocator): Promise<void> => {
  if (t3Major >= 14) {
    const menu = await openV14Dropdown(frame);
    for (const label of SWITCHER_LABELS) {
      await expect(menu.getByText(label, { exact: true })).toBeVisible();
    }
    // Close the dropdown again to leave a clean state.
    await page.keyboard.press('Escape');
    await expect(menu).toBeHidden();
  } else {
    const select = frame.locator('.module-docheader select#moduleMenu');
    await expect(select).toBeVisible();
    const options = select.locator('option');
    await expect(options).toHaveText(SWITCHER_LABELS);
  }
};

const switchToSubmodule = async (frame: FrameLocator, label: string): Promise<void> => {
  if (t3Major >= 14) {
    const menu = await openV14Dropdown(frame);
    await menu.getByText(label, { exact: true }).click();
  } else {
    const select = frame.locator('.module-docheader select#moduleMenu');
    await select.selectOption({ label });
  }
};

test('monitoring module opens with the overview', async ({ page }) => {
  const frame = await openMonitoringModule(page);

  // The overview greets with one card per submodule.
  for (const label of SUBMODULE_LABELS) {
    await expect(
      frame.locator('.card-title').getByText(label, { exact: true }),
    ).toBeVisible();
  }
});

test('submodules are reachable through the docheader switcher', async ({ page }) => {
  const frame = await openMonitoringModule(page);

  // Enter the Providers submodule via its overview card.
  await frame
    .locator('.card', { has: frame.getByText('Providers', { exact: true }) })
    .locator('.card-footer a')
    .click();

  // The regression guard: without submodule registration (and, on v13, the
  // manually built menu) this switcher is missing entirely.
  await expectSwitcherEntries(page, frame);

  await switchToSubmodule(frame, 'Reporters');
  await expectSwitcherEntries(page, frame);

  // And back to the overview.
  await switchToSubmodule(frame, OVERVIEW_LABEL);
  for (const label of SUBMODULE_LABELS) {
    await expect(
      frame.locator('.card-title').getByText(label, { exact: true }),
    ).toBeVisible();
  }
});
