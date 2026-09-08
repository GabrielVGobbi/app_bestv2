---
name: pulse-development
description: Configure or debug Laravel Pulse dashboards, recorders, ingestion, and custom cards.
license: MIT
metadata:
  author: laravel
---

# Laravel Pulse

Pulse is already installed. Inspect `config/pulse.php`, the existing provider/gate, and relevant dashboard/card before changing setup. Use Boost `search-docs` with `laravel/pulse` for the selected workflow.

- **Dashboard access:** Preserve the `viewPulse` authorization boundary; local access does not establish production access.
- **Missing metrics:** Check the relevant recorder, filters, sampling, storage connection, and schema. The Servers recorder needs `pulse:check`; Redis ingestion needs `pulse:work`. Diagnose before starting workers or running migrations.
- **Ingestion/performance:** Follow the existing driver. Verify Redis prerequisites before changing ingestion; `pulse:restart` needs a working cache driver.
- **Custom cards:** Reuse Pulse's Livewire card base and established views. Match recording and aggregation types; use Pulse's user resolver for user-keyed data.
- **Sampling:** Sampled metrics are approximate and are unsuitable as authoritative financial/audit totals.

Verify the affected recorder/card and its access rules. Do not install Pulse again, deploy workers, or change production access merely to complete a local card change.
