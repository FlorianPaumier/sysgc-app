# Identity

Identity defines who can enter SysGC and which construction organization they represent.

## Language

**Organization**:
A construction business whose data and subscription are isolated from every other business.
_Avoid_: Account, tenant

**User**:
A person authorized to act for one organization.
In the product interface, a user appears as a **member** with exactly one membership role: owner, admin, or foreman.
_Avoid_: Operator

**Invitation**:
A seven-day, single-use credential bound to one organization, one email address, one admin/foreman role, and optional project assignments.

**Project assignment**:
The explicit set of projects a foreman may view and use. Owners and admins are organization-wide and are not assigned project-by-project.

**Magic Link**:
A single-use, expiring credential delivered to a user for passwordless sign-in.
_Avoid_: Password reset link, token
