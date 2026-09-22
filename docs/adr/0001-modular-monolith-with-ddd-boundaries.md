# Use a modular monolith with DDD boundaries

SysGC is organized as bounded contexts with Domain, Application, Infrastructure, and Presentation layers inside one Symfony deployment. This preserves simple transactions and operations while making business ownership explicit; microservices were rejected because the current scale does not justify distributed consistency and deployment overhead, and horizontal MVC layers were rejected because they mix unrelated business capabilities.

The public site and authenticated application remain separate presentation compositions and frontend entry points. Contexts may share domain concepts explicitly through the context map, while domain code must not depend on Application, Infrastructure, or Presentation layers.
