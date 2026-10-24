# AI review, slice 5: admin page for health categories

Status: done, awaiting commit.

## Goal
An admin can add, rename, reorder and switch off health categories without a deploy.

## Read first
- [domain/health-ai-review/context.md](../domain/health-ai-review/context.md)
- [domain/identity-and-permissions.md](../domain/identity-and-permissions.md): admin routes and permissions
- [rules/ui-and-frontend.md](../rules/ui-and-frontend.md): skeletons, field errors, AJAX saves

## Steps
1. A permission and an admin route group entry for the module (ask the owner before adding the permission).
2. List page per farm category with skeleton placeholders; add, rename, reorder, switch on or off. Saves use AJAX.
3. Names are unique within a farm category. Never delete a category that reports use; switch it off instead.
4. Uuids only in URLs and props.

## Needs the owner
Page wording and the permission name.

## Done when
Full suite plus TypeScript check and build pass, committed, todo and handoff updated.

## Log
- 2026-10-20: task written.
- 2026-10-21: built. Owner approved permission `health-categories.manage` (hyphen, as the other modules), four routes and the wording. Farm category is identified by name in the store request (no uuid column; a column needs owner approval).
