# E2E tests

Playwright end-to-end tests for the backend module on both supported TYPO3 majors. CI runs them via
`.github/workflows/e2e.yaml` against a throwaway sqlite-based TYPO3 instance.

## Running locally

The tests expect an already running TYPO3 instance with this extension installed and a backend admin who is a
registered system maintainer:

```bash
cd Tests/Acceptance
npm ci
PLAYWRIGHT_BASE_URL=https://typo3-monitoring.ddev.site \
T3_MAJOR=14.3 \
T3_ADMIN_USERNAME=admin \
T3_ADMIN_PASSWORD=password \
npx playwright test
```

| Variable              | Default                              | Purpose                                       |
|-----------------------|--------------------------------------|-----------------------------------------------|
| `PLAYWRIGHT_BASE_URL` | `https://typo3-monitoring.ddev.site` | Base URL of the instance                      |
| `T3_MAJOR`            | `14.3`                               | TYPO3 major version under test (`13` or `14`) |
| `T3_ADMIN_USERNAME`   | `admin`                              | Backend admin username                        |
| `T3_ADMIN_PASSWORD`   | `password`                           | Backend admin password                        |

`npx playwright test --ui` opens the interactive runner.
