CREATE TABLE IF NOT EXISTS "{{prefix}}acls" (
  "page_tag" VARCHAR(191) NOT NULL,
  "privilege" VARCHAR(20) NOT NULL,
  "list" TEXT NOT NULL,
  PRIMARY KEY ("page_tag", "privilege")
);

CREATE TABLE IF NOT EXISTS "{{prefix}}links" (
  "id" SERIAL PRIMARY KEY,
  "from_tag" VARCHAR(191) NOT NULL,
  "to_tag" VARCHAR(191) NOT NULL,
  UNIQUE ("from_tag", "to_tag")
);

CREATE TABLE IF NOT EXISTS "{{prefix}}nature" (
  "bn_id_nature" SERIAL PRIMARY KEY,
  "bn_label_nature" VARCHAR(255) DEFAULT NULL,
  "bn_description" TEXT DEFAULT NULL,
  "bn_condition" TEXT DEFAULT NULL,
  "bn_sem_context" TEXT DEFAULT NULL,
  "bn_sem_type" VARCHAR(255) DEFAULT NULL,
  "bn_sem_use_template" SMALLINT NOT NULL DEFAULT 1,
  "bn_template" TEXT NOT NULL,
  "bn_ce_i18n" VARCHAR(5) NOT NULL,
  "bn_only_one_entry" VARCHAR(1) NOT NULL DEFAULT 'N' CHECK("bn_only_one_entry" IN ('Y', 'N')),
  "bn_only_one_entry_message" TEXT DEFAULT NULL
);

CREATE TABLE IF NOT EXISTS "{{prefix}}pages" (
  "id" SERIAL PRIMARY KEY,
  "tag" VARCHAR(191) NOT NULL,
  "time" TIMESTAMP NOT NULL,
  "body" TEXT NOT NULL,
  "body_r" TEXT NOT NULL,
  "owner" VARCHAR(191) NOT NULL,
  "user" VARCHAR(191) NOT NULL,
  "latest" VARCHAR(1) NOT NULL DEFAULT 'N' CHECK("latest" IN ('Y', 'N')),
  "handler" VARCHAR(30) NOT NULL DEFAULT 'page',
  "comment_on" VARCHAR(191) NOT NULL DEFAULT ''
);

CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_tag" ON "{{prefix}}pages" ("tag");
CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_time" ON "{{prefix}}pages" ("time");
CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_latest" ON "{{prefix}}pages" ("latest");
CREATE INDEX IF NOT EXISTS "{{prefix}}pages_idx_comment_on" ON "{{prefix}}pages" ("comment_on");

CREATE TABLE IF NOT EXISTS "{{prefix}}referrers" (
  "id" SERIAL PRIMARY KEY,
  "page_tag" VARCHAR(191) NOT NULL,
  "referrer" TEXT NOT NULL,
  "time" TIMESTAMP NOT NULL
);

CREATE INDEX IF NOT EXISTS "{{prefix}}referrers_idx_page_tag" ON "{{prefix}}referrers" ("page_tag");
CREATE INDEX IF NOT EXISTS "{{prefix}}referrers_idx_time" ON "{{prefix}}referrers" ("time");

CREATE TABLE IF NOT EXISTS "{{prefix}}triples" (
  "id" SERIAL PRIMARY KEY,
  "resource" VARCHAR(255) NOT NULL,
  "property" VARCHAR(255) NOT NULL,
  "value" TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS "{{prefix}}triples_idx_resource" ON "{{prefix}}triples" ("resource");
CREATE INDEX IF NOT EXISTS "{{prefix}}triples_idx_property" ON "{{prefix}}triples" ("property");

CREATE TABLE IF NOT EXISTS "{{prefix}}users" (
  "name" VARCHAR(80) NOT NULL PRIMARY KEY,
  "password" VARCHAR(256) NOT NULL,
  "email" VARCHAR(191) NOT NULL,
  "motto" TEXT NOT NULL,
  "revisioncount" INTEGER NOT NULL DEFAULT 20,
  "changescount" INTEGER NOT NULL DEFAULT 50,
  "doubleclickedit" VARCHAR(1) NOT NULL DEFAULT 'Y' CHECK("doubleclickedit" IN ('Y', 'N')),
  "signuptime" TIMESTAMP NOT NULL,
  "show_comments" VARCHAR(1) NOT NULL DEFAULT 'N' CHECK("show_comments" IN ('Y', 'N'))
);

CREATE INDEX IF NOT EXISTS "{{prefix}}users_idx_name" ON "{{prefix}}users" ("name");
CREATE INDEX IF NOT EXISTS "{{prefix}}users_idx_signuptime" ON "{{prefix}}users" ("signuptime");

-- Creation of admins group and admin user
INSERT INTO "{{prefix}}triples" ("resource", "property", "value") VALUES
('ThisWikiGroup:admins', 'http://www.wikini.net/_vocabulary/acls', '{{WikiName}}');

INSERT INTO "{{prefix}}users" ("name", "password", "email", "motto", "revisioncount", "changescount", "doubleclickedit", "signuptime", "show_comments") VALUES
('{{WikiName}}', '{{password}}', '{{email}}', '', 20, 50, 'Y', NOW(), 'N');
