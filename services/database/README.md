# BStore database lifecycle

Laravel migrations are the canonical schema. The database image only creates the four databases and grants the application user access; it does not import a data dump or pre-populate the `migrations` table.

For an existing installation, back up all four databases and deploy the service migrations normally. The hardening migrations run before the new session/refund tables and:

- convert legacy MyISAM tables to InnoDB;
- remove orphaned child rows and deterministically merge duplicate legacy rows;
- add same-service foreign keys and unique constraints;
- preserve microservice isolation by avoiding cross-database foreign keys.

`init/02-import-bstore-dump.sql` is a git-ignored legacy data snapshot. It is not executed by Docker. Its table engines have been converted to InnoDB in this workspace; schema evolution still belongs in migrations.

Generate deployment secrets with:

```powershell
.\scripts\rotate-secrets.ps1
```

Use `-IncludeDatabasePasswords` only for a fresh database or after rotating the corresponding MySQL users on an existing volume.
