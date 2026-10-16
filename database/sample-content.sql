-- LOCAL PREVIEW ONLY. NEVER IMPORT THIS FILE IN PRODUCTION. ALL ROWS ARE FICTIONAL PLACEHOLDERS.
-- Import after schema.sql and seed.sql. Delete the "[Sample]" rows before going live.
SET NAMES utf8mb4;

INSERT INTO news_articles (category_id, title, slug, excerpt, content, author_name, status, published_at, is_featured, consent_confirmed) VALUES
((SELECT id FROM news_categories WHERE slug='news-updates'), '[Sample] News Update', 'sample-news-update', 'Placeholder news excerpt.', 'This is placeholder news content.

## Sample heading

- Sample point one
- Sample point two

**Not real news.**', 'Sample Author', 'published', '2026-03-01 09:00:00', 1, 0),
((SELECT id FROM news_categories WHERE slug='announcements'), '[Sample] Announcement', 'sample-announcement', 'Placeholder announcement excerpt.', 'Placeholder announcement content.

A second paragraph of placeholder text.', 'Sample Author', 'published', '2026-02-01 09:00:00', 0, 0),
((SELECT id FROM news_categories WHERE slug='success-stories'), '[Sample] Success Story', 'sample-success-story', 'Placeholder story excerpt.', 'Placeholder story content. No real person is described.

## Sample heading

Another placeholder paragraph.', 'Sample Author', 'published', '2026-01-15 09:00:00', 0, 1),
((SELECT id FROM news_categories WHERE slug='events'), '[Sample] Draft Article', 'sample-draft-article', 'Placeholder draft excerpt.', 'Draft placeholder content that must not appear publicly.', 'Sample Author', 'draft', NULL, 0, 0);
