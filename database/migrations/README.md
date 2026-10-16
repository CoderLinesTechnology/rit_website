# Database migrations

`database/schema.sql` creates the initial schema (version `0001_initial`) and `database/seed.sql`
adds the required starting content. Both are imported once on a fresh database.

Every later schema change goes in a new file named `NNNN_description.sql`
(for example `0002_add_event_table.sql`), numbered in sequence after the last one.

- Files are applied in numeric order and never edited after they have been released.
- Each applied file is recorded in the `schema_migrations` table (`version` = file name without `.sql`).
- The runner script skips versions already recorded, so running it twice is safe.
- Keep migrations compatible with MySQL 8.0+ and MariaDB 10.4 (utf8mb4_unicode_ci, DATETIME in UTC, no JSON type).
- Back up the database before applying migrations in production.
