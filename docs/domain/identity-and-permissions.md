# Identity, routing and permissions

## Identity
- `farmer_profiles.uuid`. UUIDs appear in all URLs; row ids never reach the browser.
- Phone numbers are stored in local format (`0244445566`) and normalised at every entry point with `Phone::normalise()`. Arkesel accepts the local format, so nothing is converted when sending.
- "Supervisor" means agent. There is no separate role.
- Invited staff (agent, vet, adviser, supplier) set their own password. Activated accounts can be disabled but never deleted. Unactivated invitations can be cancelled.

## Admin routes
Two groups, both prefixed `admin` and named `admin.`: one for the dashboard and permissions (role-gated), and one for every other module, where each action is wrapped in `access:{module}.{action}`.

## Permissions
- `PermissionsSeeder` syncs permissions. `RolesAndPermissionsSeeder` must be seeded first in tests.
- Always use `$this->access->can()`, never Laravel's `$user->can()`.
- Spatie caches permissions for 24 hours. Run `permission:cache-reset` after any permission change and in every deploy.
- Adding a permission to config does not grant it. Re-run `PermissionsSeeder`.
- Components: `AccessControlService` and the `CheckPermission` middleware.
