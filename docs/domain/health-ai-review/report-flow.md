# Report flow

- A farmer reports a problem on a farm unit with one photo, a description and an optional voice note (up to 30 seconds).
- The report is routed by the farm unit category: Crop goes to an adviser; Livestock and Aquatic go to a vet (`OfficerRole::fromFarmCategory`). Routing is in `ReportRoutingService`. The officer is the one linked to the farmer agent; with none, the report waits in the admin queue.
- The officer responds with a status, a contact method and a note.
- Offline reports are saved as text first (status `waiting_for_photo`) and are invisible to everyone until the photo arrives in chunks (`HealthReportUploadService`). Then `ReportReleaseService` routes and notifies, exactly once.
- A farm unit past reports are shown to the officer as history.
- Public ids only: reports use uuids in URLs.
