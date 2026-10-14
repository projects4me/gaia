-- Projects4Me Copyright (c) 2017. Licensing : http://legal.projects4.me/LICENSE.txt. Do not remove this line
--
-- Application settings (Setting model, table `settings`).
-- The migration creates the table; this seed creates the rows the Settings API reads and updates.
-- Idempotent: safe to run on every environment and every deploy.
--   psql -d <database> -f db/seeds/settings.sql
-- Values start empty: email falls back to the SMTP_* env vars until a value is saved.
-- IDs are deterministic (uuid5 of the setting name) so every environment has the same rows.

INSERT INTO settings (id, name, "groupName", value, secure, "dateCreated", "dateModified", deleted, "createdUser", "createdUserName", "modifiedUser", "modifiedUserName")
VALUES
    ('d3ed81e7-bfc2-5382-8032-b2b17c450039', 'email.smtpHost', 'email', NULL, FALSE, NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('dd8caf82-9091-573c-b753-576346dd9500', 'email.smtpPort', 'email', NULL, FALSE, NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('fa6c9c5d-bc32-596b-90db-5ead3ddc6009', 'email.smtpEncryption', 'email', NULL, FALSE, NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('23131b90-65c9-5b8d-992f-50949a0d06c1', 'email.smtpUsername', 'email', NULL, FALSE, NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('ee0416ea-a6ce-5e83-abcb-ab0a5cefa53f', 'email.smtpPassword', 'email', NULL, TRUE, NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('b4d38728-4a3a-50bb-a67d-fd2b7e940b68', 'email.fromName', 'email', NULL, FALSE, NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan'),
    ('b727da8f-a3fd-5913-b74a-2afcb43a9d62', 'email.fromEmail', 'email', NULL, FALSE, NOW(), NOW(), FALSE, '1', 'Hammad Hassan', '1', 'Hammad Hassan')
ON CONFLICT (name) DO NOTHING;
