# USSD wording and simulator

- All USSD wording is in `resources/ussd.en.json` and `resources/ussd.tw.json`, read through `UssdText`. A screen is a key in these files.
- Screens are built in `UssdScreens`; the step-by-step flow is in `UssdFlow`, loan entry in `UssdLoanFlow`, recording in `UssdRecorder`.
- A reply must fit in 182 characters (see [callback.md](callback.md)).
- A local simulator plays a session without a phone: the command `php artisan ussd:simulate {phone}` and a page. It refuses to run in production.
- Tests are in `tests/Feature/Ussd/`. Menu numbers are asserted in several test files, so changing a menu order means updating those tests.
