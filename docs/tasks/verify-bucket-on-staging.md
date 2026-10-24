# Verify the Railway Bucket on staging

Status: waiting on the owner. Not code.

## Goal
Confirm report photos are stored in and served from the Railway Bucket.

## Read first
- [ops/deployment-railway.md](../ops/deployment-railway.md)
- [ops/env-and-seeders.md](../ops/env-and-seeders.md): the variable names

## Steps (owner)
1. On the app service in the staging environment set `PHOTO_DISK=s3` and check the `AWS_*` variables exist with those exact names. Try `AWS_USE_PATH_STYLE_ENDPOINT=true` if uploads fail with a host or endpoint error.
2. Redeploy.
3. As a farmer, submit one report with a photo. As the officer, open it and check the photo loads.
4. In the Bucket, look for a `disease-reports/` folder with a `.webp` file.

## Report back
Whether it worked, or the exact error text. Never send secret values.

## Log
- 2026-10-20: written. Bucket connected to the service by the owner; `PHOTO_DISK` not yet confirmed.
