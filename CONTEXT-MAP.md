# Context Map

## Contexts

- [Identity](./src/Identity/CONTEXT.md): owns organizations, users, addresses, and passwordless access.
- [Project](./src/Project/CONTEXT.md): owns construction projects and their lifecycle.
- [Workforce](./src/Workforce/CONTEXT.md): owns workers, trades, regions, and hours worked.
- [Compliance](./src/Compliance/CONTEXT.md): evaluates CCQ declarations and RBQ licences.
- [Safety](./src/Safety/CONTEXT.md): records project safety observations.
- [Commercial](./src/Commercial/CONTEXT.md): owns change orders and invoices.
- [Subscription](./src/Subscription/CONTEXT.md): owns plan selection and Stripe billing workflows.
- [Public Site](./src/PublicSite/CONTEXT.md): owns unauthenticated marketing and contact journeys.
- [Shared](./src/Shared/CONTEXT.md): contains the small technical kernel shared by contexts.

## Relationships

- **Project → Identity**: a project belongs to an organization.
- **Workforce → Identity**: a worker and hours entry belong to an organization.
- **Compliance → Project and Workforce**: compliance evaluates project parties and CCQ workforce data.
- **Safety and Commercial → Project**: safety logs, change orders, and invoices refer to a project.
- **Subscription → Identity**: billing changes the organization subscription tier.
- **Public Site → Shared** and **Identity → Shared**: both send email through shared infrastructure, but their presentation logic and frontend assets remain independent.

