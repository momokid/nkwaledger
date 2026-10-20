# Code design

Every service, controller and model follows four principles: dependency injection, pure functions, separation of concerns, loose coupling.

- Controller: takes the request, calls a service, returns a response. No queries, no business logic.
- Service: the business rules.
- Model: data and relationships.
- Request class: validation.
- Pass dependencies in; never create them inside a class.
- Keep functions free of hidden side effects where possible.
- Depend on a contract only when there is a real reason to swap it. Do not add an interface with one implementation.
- Minimal code. No over-engineering. No unrequested packages, columns, routes, permissions, UI or wording.
- Comments: very short, only where the logic is not obvious. Never describe what was "added" or "changed".
- One issue per task. If you find other problems, report them; do not fix them.
