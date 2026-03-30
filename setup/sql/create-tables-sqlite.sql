CREATE TABLE IF NOT EXISTS "{{prefix}}acls" (
  "page_tag" TEXT NOT NULL,
  "privilege" TEXT NOT NULL,
  "list" TEXT NOT NULL,
  PRIMARY KEY ("page_tag", "privilege")
);

CREATE TABLE IF NOT EXISTS "{{prefix}}links" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "from_tag" TEXT NOT NULL,
  "to_tag" TEXT NOT NULL,
  UNIQUE ("from_tag", "to_tag")
);

CREATE TABLE IF NOT EXISTS "{{prefix}}nature" (
  "bn_id_nature" INTEGER PRIMARY KEY AUTOINCREMENT,
  "bn_label_nature" TEXT DEFAULT NULL,
  "bn_description" TEXT DEFAULT NULL,
  "bn_condition" TEXT DEFAULT NULL,
  "bn_sem_context" TEXT DEFAULT NULL,
  "bn_sem_type" TEXT DEFAULT NULL,
  "bn_sem_use_template" INTEGER NOT NULL DEFAULT 1,
  "bn_template" TEXT NOT NULL,
  "bn_ce_i18n" TEXT NOT NULL,
  "bn_only_one_entry" TEXT NOT NULL DEFAULT 'N' CHECK("bn_only_one_entry" IN ('Y', 'N')),
  "bn_only_one_entry_message" TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "{{prefix}}pages" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "tag" TEXT NOT NULL,
  "time" TEXT NOT NULL,
  "body" TEXT NOT NULL,
  "body_r" TEXT NOT NULL,
  "owner" TEXT NOT NULL,
  "user" TEXT NOT NULL,
  "latest" TEXT NOT NULL DEFAULT 'N' CHECK("latest" IN ('Y', 'N')),
  "handler" TEXT NOT NULL DEFAULT 'page',
  "comment_on" TEXT NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_tag" ON "{{prefix}}pages" ("tag");
CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_time" ON "{{prefix}}pages" ("time");
CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_latest" ON "{{prefix}}pages" ("latest");
CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_comment_on" ON "{{prefix}}pages" ("comment_on");

CREATE TABLE IF NOT EXISTS "{{prefix}}referrers" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "page_tag" TEXT NOT NULL,
  "referrer" TEXT NOT NULL,
  "time" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "{{prefix}}referrers_idx_page_tag" ON "{{prefix}}referrers" ("page_tag");
CREATE INDEX IF NOT EXISTS "{{prefix}}referrers_idx_time" ON "{{prefix}}referrers" ("time");

CREATE TABLE IF NOT EXISTS "{{prefix}}triples" (
  "id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "resource" TEXT NOT NULL,
  "property" TEXT NOT NULL,
  "value" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "{{prefix}}triples_idx_resource" ON "{{prefix}}triples" ("resource");
CREATE INDEX IF NOT EXISTS "{{prefix}}triples_idx_property" ON "{{prefix}}triples" ("property");

CREATE TABLE IF NOT EXISTS "{{prefix}}users" (
  "name" TEXT NOT NULL PRIMARY KEY,
  "password" TEXT NOT NULL,
  "email" TEXT NOT NULL,
  "motto" TEXT NOT NULL,
  "revisioncount" INTEGER NOT NULL DEFAULT 20,
  "changescount" INTEGER NOT NULL DEFAULT 50,
  "doubleclickedit" TEXT NOT NULL DEFAULT 'Y' CHECK("doubleclickedit" IN ('Y', 'N')),
  "signuptime" TEXT NOT NULL,
  "show_comments" TEXT NOT NULL DEFAULT 'N' CHECK("show_comments" IN ('Y', 'N'))
);

CREATE INDEX IF NOT EXISTS "{{prefix}}users_idx_name" ON "{{prefix}}users" ("name");
CREATE INDEX IF NOT EXISTS "{{prefix}}users_idx_signuptime" ON "{{prefix}}users" ("signuptime");

-- Creation of admins group and admin user
INSERT INTO "{{prefix}}triples" ("resource", "property", "value") VALUES
('ThisWikiGroup:admins', 'http://www.wikini.net/_vocabulary/acls', '{{WikiName}}');

INSERT INTO "{{prefix}}users" ("name", "password", "email", "motto", "revisioncount", "changescount", "doubleclickedit", "signuptime", "show_comments") VALUES
('{{WikiName}}', '{{password}}', '{{email}}', '', 20, 50, 'Y', datetime('now'), 'N');
