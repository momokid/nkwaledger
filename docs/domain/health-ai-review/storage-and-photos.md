# Photos and storage

- `PhotoUpload` resizes every photo to at most 1600 pixels on the longest side, fixes its orientation, and stores it as WebP at 75% quality. The AI reads this stored copy.
- Web reports store the photo and the voice note on the disk in `PHOTO_DISK` and record that disk in `media_disk` on the report. The offline upload path does the same.
- Production uses a Railway Bucket (`PHOTO_DISK=s3`). The S3 package `league/flysystem-aws-s3-v3` is installed. Setup and variables: [../../ops/deployment-railway.md](../../ops/deployment-railway.md) and [../../ops/env-and-seeders.md](../../ops/env-and-seeders.md).
- Older reports keep their old `media_disk` value, so they still load from where they are. Files on the container local disk are lost on redeploy.
- Private files are served through the media route (`DiseaseReportMediaController`), not by a public link.
- Tests that submit reports must fake the photo disk, or they write real files (see [../../ops/gotchas.md](../../ops/gotchas.md)).
- Staging check still to do: [../../tasks/verify-bucket-on-staging.md](../../tasks/verify-bucket-on-staging.md).
