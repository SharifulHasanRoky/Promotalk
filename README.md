# Promotalk

An all-in-one CRM and marketing platform — think GoHighLevel — built as a multi-tenant
modular monolith.

> **Status: scaffold.** The project skeleton, multi-tenant data model, sidebar/app
> shell, and a stub for every major module are in place. Most modules ship as
> "coming soon" placeholders that document the planned capabilities. Contacts,
> Dashboard, and the Contacts API are wired end-to-end as the first vertical slice.

---

## Tech stack

| Layer            | Choice                                                         |
| ---------------- | -------------------------------------------------------------- |
| Web framework    | [Next.js 14](https://nextjs.org) (App Router, Server Components) |
| Language         | TypeScript                                                     |
| Styling          | Tailwind CSS + a few shadcn-style primitives                   |
| Icons            | `lucide-react`                                                 |
| ORM / DB         | Prisma + PostgreSQL                                            |
| Auth             | NextAuth (Credentials provider scaffolded)                     |
| Validation       | Zod                                                            |

## Architecture

### Multi-tenancy
```
Agency
  └── SubAccount (a.k.a. Location)
        ├── Users (via Membership)
        ├── Contacts, Tags, Custom fields
        ├── Pipelines -> Stages -> Opportunities
        ├── Calendars -> Appointments
        ├── Conversations -> Messages
        ├── Campaigns, Workflows
        ├── Forms -> Submissions
        ├── Sites / Funnels
        ├── Products (Payments)
        └── Reviews (Reputation)
```
Every domain row carries a `subAccountId`, so queries can be scoped per tenant
with a single `where` clause. Agency-level rollups read across the agency's
sub-accounts.

### Routing layout
- `src/app/(auth)/*` — public auth pages (`/login`, `/register`)
- `src/app/(app)/*` — authenticated app shell with sidebar
  - One folder per module (`contacts`, `pipelines`, `conversations`, ...)
  - Each module is independent: data model, routes, and (eventually) services
- `src/app/api/*` — REST endpoints

### Module catalogue
The sidebar is generated from `src/lib/modules.ts`. Adding a new module is:
1. Add an entry to that file.
2. Create `src/app/(app)/<slug>/page.tsx`.
3. (Optional) add Prisma models and an API route.

---

## Local development

```bash
# 1. Install deps
npm install

# 2. Copy the env template and point DATABASE_URL at a Postgres instance
cp .env.example .env

# 3. Generate the Prisma client and create the schema
npm run db:generate
npm run db:push

# 4. Seed an agency, sub-account, contacts, and a pipeline
npm run db:seed

# 5. Run the app
npm run dev
```

Open <http://localhost:3000>. The landing page links into `/dashboard` and a tile
for every module.

### Useful scripts
| Script              | What it does                                       |
| ------------------- | -------------------------------------------------- |
| `npm run dev`       | Next.js dev server                                 |
| `npm run build`     | Production build                                   |
| `npm run typecheck` | `tsc --noEmit`                                     |
| `npm run db:push`   | Sync the Prisma schema to the database (no migrations) |
| `npm run db:migrate`| Create + apply a migration                         |
| `npm run db:seed`   | Run `prisma/seed.ts`                               |

---

## Roadmap

The scaffold is designed to be filled in module-by-module. Recommended order:

1. **Auth + tenancy** — finish NextAuth, sub-account switcher in the topbar, RBAC middleware.
2. **Contacts** — CRUD UI, tags, custom fields, CSV import, filters and saved views.
3. **Pipelines** — kanban board, drag-and-drop, opportunity drawer.
4. **Calendars** — public booking page, Google sync, appointment lifecycle.
5. **Conversations** — Twilio SMS first, then email and webchat; unified inbox UI.
6. **Workflows** — visual builder + a runner service that reacts to events.
7. **Campaigns** — audience builder reusing CRM filters; uses the workflow runner.
8. **Forms** — drag-and-drop builder, embed snippet, contact-on-submit.
9. **Sites/Funnels** — block editor, custom domains, hosted rendering.
10. **Payments** — Stripe Connect, products, hosted checkout, invoices.
11. **Memberships** — courses on top of the existing tenancy.
12. **Reputation** — review request automations and Google/Facebook integrations.
13. **Reporting** — cross-module analytics and agency-level rollups.

Each step has hooks already in place: the Prisma models exist, the routes are
registered, and the sidebar entry is live.

---

## Project layout

```
prisma/
  schema.prisma         # multi-tenant data model for every module
  seed.ts               # demo agency + sub-account + contacts + pipeline
src/
  app/
    layout.tsx          # root html/body
    page.tsx            # marketing landing page
    globals.css
    (auth)/             # /login, /register
    (app)/              # authenticated shell
      layout.tsx        # sidebar + topbar
      dashboard/
      contacts/
      conversations/
      calendars/
      pipelines/
      campaigns/
      workflows/
      forms/
      sites/
      memberships/
      reputation/
      payments/
      reporting/
      settings/
    api/
      auth/[...nextauth]/route.ts
      contacts/route.ts
  components/
    app-sidebar.tsx
    app-topbar.tsx
    page-header.tsx
    module-stub.tsx
  lib/
    auth.ts             # NextAuth options (Credentials)
    db.ts               # Prisma client singleton
    modules.ts          # sidebar / landing-page module catalogue
    utils.ts
```
