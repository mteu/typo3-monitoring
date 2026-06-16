# EmailReporter

Sends the monitoring status by email through TYPO3's core mailer
(`MailerInterface`). See [Reporters](../reporters.md) for the general dispatch
flow and thresholds.

## Configuration

```php
'EXTENSIONS' => [
    'monitoring' => [
        'reporter' => [
            'mteu\Monitoring\Reporter\EmailReporter' => [
                'enabled' => true,
                'priority' => 10,
                'threshold' => 'state-change',
                'recipients' => 'ops@example.com, oncall@example.com',
                'senderAddress' => 'typo3@example.com',
                'subjectPrefix' => '[Monitoring]',
            ],
        ],
    ],
];
```

| Setting         | Default        | Notes                                                            |
|-----------------|----------------|------------------------------------------------------------------|
| `enabled`       | `false`        | Master switch.                                                   |
| `priority`      | `10`           | Higher is reported first.                                        |
| `threshold`     | `state-change` | `always`, `unhealthy`, or `state-change`.                        |
| `recipients`    | `''`           | Comma-separated addresses. **Required** — empty means inactive.  |
| `senderAddress` | `''`           | Blank falls back to TYPO3's `defaultMailFromAddress`.            |
| `subjectPrefix` | `[Monitoring]` | Prepended to every subject; blank for none.                      |

The reporter is **active** only when `enabled` is `true` *and* at least one
recipient is configured.

## Transport

SMTP / transport credentials are **not** part of this extension's
configuration — they come from TYPO3's global `MAIL` settings
(`$GLOBALS['TYPO3_CONF_VARS']['MAIL']`). A transport failure is logged and
`report()` returns `false`, so the dispatcher retries on the next run.

## Message

Subject and body vary by decision:

| Decision    | Subject (after prefix)         | Body opening                                          |
|-------------|--------------------------------|------------------------------------------------------|
| `Unhealthy` | `Unhealthy: <names>`           | The following services are reporting an unhealthy state: |
| `Reminder`  | `Still unhealthy: <names>`     | The following services are still reporting an unhealthy state: |
| `Recovered` | `All monitored services recovered` | All monitored services are healthy again.        |

The body then lists every active provider as `[OK] Name` / `[FAILED] Name — reason`.
