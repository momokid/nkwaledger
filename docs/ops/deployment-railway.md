# Deployment on Railway

- Two environments, production and staging. Staging follows `develop`; production follows `master`.
- The app runs in Docker (php-fpm + nginx + supervisor). CI is GitHub Actions; the check a pull request waits for is the job named `test`, which runs PostgreSQL.
- Report photos and voice notes: use a Railway Bucket (S3-compatible) with `PHOTO_DISK=s3`. A Volume is a poorer fit: it belongs to one service and cannot be shared. Files on the container local disk are lost on redeploy.
- Variables are per environment: set them in the app service Variables tab, in each environment that needs them.
- Reports submitted before the move to the Bucket stay on the old disk. The `media_disk` column on each report says where its files are.

## Older notes, not re-checked
These come from the archived phase-2 handoff: Railway had no scheduler, so `verification:expire` never ran in production; no WAF sat in front of Railway; no hard SMS spend cap was set on Arkesel. Verify before relying on them.
