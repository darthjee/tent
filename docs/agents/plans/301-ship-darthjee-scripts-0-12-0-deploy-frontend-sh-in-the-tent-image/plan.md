# Plan: Ship darthjee/scripts 0.12.0 deploy_frontend.sh in the tent image

Issue: [301-ship-darthjee-scripts-0-12-0-deploy-frontend-sh-in-the-tent-image.md](../../issues/301-ship-darthjee-scripts-0-12-0-deploy-frontend-sh-in-the-tent-image.md)

## Overview
Switch the tent image's `scripts` stage to the multi-arch `darthjee/scripts:0.12.0`, dropping the amd64 pin from #300, and add a tent-only image check that guards `deploy_frontend.sh` and its runtime tools on both arches.

See [infra.md](infra.md) for the full plan.
