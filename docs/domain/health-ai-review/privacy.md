# Privacy of the AI review

- Only the photo, the description, the farm type and the list of allowed categories are sent. No name, phone number, location or EXIF data.
- The request asks OpenRouter to use only providers that do not keep or train on the data (`provider.data_collection = deny`).
- The voice note is not sent.
- Photos still leave the country to a third-party provider, so the privacy policy and consent text in the MVP close must say so (see [../../todo.md](../../todo.md)).
- Never log photo contents or the API key.
- General security rules: [../../rules/security.md](../../rules/security.md).
