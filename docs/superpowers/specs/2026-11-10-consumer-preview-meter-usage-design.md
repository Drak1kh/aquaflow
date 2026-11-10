# Consumer Preview Meter Usage Design

## Goal

Align a consumer preview's meter-reading activity with Meter Reading history by showing monthly usage without exposing raw previous or current meter totals.

## Behavior

- Keep the existing account-activity table structure, meter reference, month, billing, payments, and permissions.
- For reading rows, replace `previous → current · usage` with the calculated monthly usage formatted to three decimals and `m³`.
- Do not change meter-reading storage, recording, billing, reporting, or historical data.

## Verification

- Add a focused feature assertion that the preview displays the calculated usage and omits raw meter totals.
