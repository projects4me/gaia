# client-contract

Client/frontend-derived inputs for the optional `generate-client-apis.php` baseline.

| File | Purpose |
|---|---|
| `test-candidates.json` | Prioritized endpoint backlog used by `generate-client-apis.php` |

Note: `apis/client.json` is the hand-tuned client catalog source of truth. Re-running `generate-client-apis.php` overwrites it with a naive baseline (mutations disabled). Prefer editing `client.json` directly.
