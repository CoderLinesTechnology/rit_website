-- RIT Foundation Ghana - required initial content (verified facts only).
-- Import ONCE, after schema.sql. Empty-string settings mean "to be completed by the administrator".
SET NAMES utf8mb4;

INSERT INTO program_categories (name, slug, description, icon, sort_order) VALUES
('Technology and Digital Skills', 'technology-and-digital-skills', 'RIT Foundation Ghana empowers young women, girls, and youth through technical, vocational, digital, entrepreneurial, and practical skills development.', 'laptop', 1),
('Technical and Vocational Skills', 'technical-and-vocational-skills', 'RIT Foundation Ghana empowers young women, girls, and youth through technical, vocational, digital, entrepreneurial, and practical skills development.', 'wrench', 2),
('Fashion and Creative Skills', 'fashion-and-creative-skills', 'Its programs help participants discover their talents, develop confidence, gain practical skills, and explore employment and entrepreneurship.', 'scissors', 3),
('Entrepreneurship and Business Skills', 'entrepreneurship-and-business-skills', 'Its programs help participants explore employment and entrepreneurship and work toward financial independence.', 'graph-up-arrow', 4),
('Youth Development and Leadership', 'youth-development-and-leadership', 'Its activities support education, innovation, leadership, self-reliance, and stronger communities.', 'people', 5),
('Community Skills Development', 'community-skills-development', 'Its activities support education, innovation, leadership, self-reliance, and stronger communities.', 'house-heart', 6);

INSERT INTO news_categories (name, slug, sort_order) VALUES
('News & Updates', 'news-updates', 1),
('Announcements', 'announcements', 2),
('Events', 'events', 3),
('Success Stories', 'success-stories', 4),
('Programs', 'programs', 5),
('Community', 'community', 6);

INSERT INTO gallery_categories (name, slug, description, event_date, external_url, is_published, sort_order) VALUES
('Tech Skills Challenge Season 2', 'tech-skills-challenge-season-2', 'Photographs from the Tech Skills Challenge Season 2 grand finale.', NULL, 'https://sediarts93.pixieset.com/techskillsgrandfinale/', 1, 1),
('Training Workshops', 'training-workshops', NULL, NULL, NULL, 1, 2),
('Fashion and Vocational Training', 'fashion-and-vocational-training', NULL, NULL, NULL, 1, 3),
('Exhibitions and Events', 'exhibitions-and-events', NULL, NULL, NULL, 1, 4),
('Community Activities', 'community-activities', NULL, NULL, NULL, 1, 5);

INSERT INTO programs (category_id, icon, title, slug, summary, description, accepts_applications, status, is_featured, sort_order) VALUES
((SELECT id FROM program_categories WHERE slug='technology-and-digital-skills'), 'laptop', 'Teens in Tech Initiative', 'teens-in-tech-initiative', 'An initiative that introduces teenagers to technology and digital skills, helping to bridge the gap between education and employability.', CONCAT('An initiative that introduces teenagers to technology and digital skills, helping to bridge the gap between education and employability.', CHAR(10), CHAR(10), 'More details about this program, including how and when to apply, will be published here.'), 0, 'published', 1, 1),
((SELECT id FROM program_categories WHERE slug='technology-and-digital-skills'), 'display', 'Digital Skills Development Program', 'digital-skills-development-program', 'Practical digital skills training for young people preparing for work and enterprise in the digital economy.', CONCAT('Practical digital skills training for young people preparing for work and enterprise in the digital economy.', CHAR(10), CHAR(10), 'More details about this program, including how and when to apply, will be published here.'), 0, 'published', 1, 2),
((SELECT id FROM program_categories WHERE slug='fashion-and-creative-skills'), 'scissors', 'Fashion Design Training for National Service Personnel', 'fashion-design-training-for-national-service-personnel', 'Fashion design training for national service personnel, building practical skills for self-employment.', CONCAT('Fashion design training for national service personnel, building practical skills for self-employment.', CHAR(10), CHAR(10), 'More details about this program, including how and when to apply, will be published here.'), 0, 'published', 1, 3),
((SELECT id FROM program_categories WHERE slug='technical-and-vocational-skills'), 'recycle', 'Zero-Waste Vocational and Technical Training with Upcycling Practices', 'zero-waste-vocational-and-technical-training-with-upcycling-practices', 'Vocational and technical training that integrates zero-waste and upcycling practices.', CONCAT('Vocational and technical training that integrates zero-waste and upcycling practices.', CHAR(10), CHAR(10), 'More details about this program, including how and when to apply, will be published here.'), 0, 'published', 1, 4),
((SELECT id FROM program_categories WHERE slug='entrepreneurship-and-business-skills'), 'graph-up-arrow', 'Entrepreneurship Seminars', 'entrepreneurship-seminars', 'Seminars that help young people develop business skills and explore entrepreneurship.', CONCAT('Seminars that help young people develop business skills and explore entrepreneurship.', CHAR(10), CHAR(10), 'More details about this program, including how and when to apply, will be published here.'), 0, 'published', 1, 5),
((SELECT id FROM program_categories WHERE slug='youth-development-and-leadership'), 'award', 'Talent Discovery and Development', 'talent-discovery-and-development', 'Helping young people discover their talents and develop them with confidence.', CONCAT('Helping young people discover their talents and develop them with confidence.', CHAR(10), CHAR(10), 'More details about this program, including how and when to apply, will be published here.'), 0, 'published', 1, 6);

INSERT INTO impact_metrics (label, value, is_approximate, description, icon, reporting_period, status, show_on_homepage, sort_order) VALUES
('Young women, girls and youth reached', '576', 1, 'Approximate number of young women, girls and youth reached through RIT Foundation Ghana programs and activities.', 'people-fill', NULL, 'published', 1, 1),
('Core program areas', '6', 0, 'Number of program areas — confirm with the Foundation before publishing.', 'mortarboard-fill', NULL, 'draft', 0, 2);

INSERT INTO social_links (platform, label, url, sort_order, is_active) VALUES
('facebook', 'Facebook', 'https://www.facebook.com/share/1HpPi7DdDT/', 1, 1),
('linkedin', 'LinkedIn', 'https://www.linkedin.com/company/rit-foundation-ghana/', 2, 1),
('instagram', 'Instagram', 'https://www.instagram.com/ritskillsgh?stkn=MXhwN2NmZGpqa3lpbw==', 3, 1),
('tiktok', 'TikTok', 'https://www.tiktok.com/@ritfoundationgh?_r=1&_t=ZS-9AHl4jCpZ6D', 4, 1),
('x', 'X', 'https://x.com/Ritfoundationof', 5, 1),
('youtube', 'YouTube', 'https://youtube.com/@ritfoundation?si=_V9xDhJ6N6Cp6L_e', 6, 1),
('whatsapp', 'WhatsApp Channel', 'https://whatsapp.com/channel/0029Vb8spP8KbYMGhGSrTM29', 7, 1);

INSERT INTO milestones (period_label, title, description, is_verified, is_published, sort_order) VALUES
('2020', 'Foundation established', 'RIT Foundation Ghana established.', 1, 1, 1),
('2021', 'Foundation registered', 'Officially registered as a non-governmental organization under the Companies Act, 2019 (Act 992).', 1, 1, 2);

INSERT INTO team_members (full_name, role_title, bio, sort_order, is_published, consent_confirmed) VALUES
('Deborah Morkporkpor Zottor', 'Founder and Director', 'MPhil Curriculum and Pedagogic Studies; Bachelor of Science, ICT Education.', 1, 1, 1);

INSERT INTO seo_meta (page_key, meta_title, meta_description) VALUES
('home', NULL, NULL), ('about', NULL, NULL), ('programs', NULL, NULL), ('impact', NULL, NULL),
('gallery', NULL, NULL), ('news', NULL, NULL), ('get-involved', NULL, NULL), ('volunteer', NULL, NULL),
('partner', NULL, NULL), ('apply', NULL, NULL), ('contact', NULL, NULL), ('donate', NULL, NULL), ('privacy', NULL, NULL), ('terms', NULL, NULL);

INSERT INTO site_settings (setting_key, setting_value) VALUES
('org.name', 'RIT Foundation Ghana'),
('org.tagline', 'Empowering young women, girls and youth through practical skills'),
('org.mission', 'To deliver gender responsive transformative education and skill development across West Africa through cutting-edge technical training, entrepreneurial development, and sustainability practices, enabling them to become innovative leaders and architects of positive change in their communities and the region.'),
('org.overview', 'RIT Foundation Ghana empowers young women, girls, and youth through technical, vocational, digital, entrepreneurial, and practical skills development. Its programs help participants discover their talents, develop confidence, gain practical skills, explore employment and entrepreneurship, and work toward financial independence. Its activities support education, innovation, leadership, self-reliance, and stronger communities. To date, the Foundation has reached approximately 576 young women, girls and youth.'),
('org.vision', 'To be West Africa''s premier platform advancing gender responsive empowerment through innovative vocational and technical education, entrepreneurship, and sustainable development, creating pathways for their economic independence and leadership in the digital economy.'),
('org.values', CONCAT('Passion', CHAR(10), 'Discipline', CHAR(10), 'Perseverance', CHAR(10), 'Creativity', CHAR(10), 'Time Consciousness', CHAR(10), 'Diligence', CHAR(10), 'Excellence', CHAR(10), 'Leadership', CHAR(10), 'Hard Work')),
('org.motto', 'Arte Divitiis sit'),
('org.established_year', '2020'),
('org.registration_details', 'Registered in 2021 under the Companies Act, 2019 (Act 992). Registration No. CG173310421.'),
('home.hero_eyebrow', 'Empower • Skill • Transform'),
('home.hero_heading', 'Equipping Young Women, Girls and Youth for a Brighter Future'),
('home.hero_text', 'RIT Foundation Ghana provides technical, vocational, digital and entrepreneurial skills development to help young people discover their talents, build confidence and create sustainable opportunities.'),
('home.hero_script', 'Skills Create Opportunities'),
('home.hero_image', ''),
('home.hero_image_alt', ''),
('home.intro_eyebrow', 'About us'),
('home.intro_heading', 'Who We Are'),
('home.intro_text', 'RIT Foundation Ghana is a nonprofit organization committed to empowering young women, girls and youth through technical, vocational, digital and entrepreneurial skills development. To date, we have reached approximately 576 young women, girls and youth through training programmes, workshops, exhibitions and skills development initiatives.'),
('home.intro_image', ''),
('home.intro_image_alt', ''),
('home.impact_eyebrow', 'Our impact'),
('home.impact_heading', 'Creating Real Change'),
('home.impact_text', 'Through our programs and initiatives, we have empowered approximately 576 young women, girls and youth in Ghana.'),
('home.programs_eyebrow', 'Our programs'),
('home.programs_heading', 'Skills for Today. Opportunities for Tomorrow.'),
('home.gallery_eyebrow', 'Gallery & events'),
('home.gallery_heading', 'Moments That Inspire'),
('home.gallery_text', 'Explore photos from our training sessions, exhibitions, workshops and the Tech Skills Challenge.'),
('home.gallery_image', ''),
('home.gallery_image_alt', ''),
('home.news_heading', 'Latest News & Success Stories'),
('home.involve_heading', 'Get Involved'),
('home.involve_text', 'Partner with us, volunteer, support our programs or make a donation. Together, we can create more opportunities.'),
('programs.hero_eyebrow', 'Our programs'),
('programs.hero_heading', 'Skills for a Brighter Future'),
('programs.hero_text', 'At RIT Foundation Ghana, we provide practical, hands-on training to help young women, girls and youth gain the skills, confidence and opportunities they need to build sustainable livelihoods and create lasting change.'),
('programs.hero_image', ''),
('programs.hero_image_alt', ''),
('programs.intro_heading', 'Explore Our Program Areas'),
('programs.intro_text', 'We offer a range of skills development programs designed to empower young people, build confidence and open doors to employment, entrepreneurship and leadership.'),
('programs.cta_heading', 'Ready to Build Your Future?'),
('programs.cta_text', 'Apply today for our training programs and take the next step towards your goals.'),
('contact.email', 'ritfoundationofficial@gmail.com'),
('contact.phone', '+233 55 494 2195'),
('contact.phone_secondary', '+233 24 251 5127'),
('contact.address', 'Sogasco Street, Sogakope, Volta Region, Ghana'),
('contact.office_hours', ''),
('contact.map_embed_url', ''),
('applications.enabled', '1'),
('applications.closed_message', 'Applications are not open at the moment. Please check back soon or follow us on social media for announcements.'),
('applications.allow_attachment', '1'),
('donations.notice', 'Online donations are not available yet. We are setting up a secure payment option.'),
('donations.offline_instructions', ''),
('notify.email', 'ritfoundationofficial@gmail.com'),
('notify.send_confirmations', '1'),
('footer.text', 'RIT Foundation Ghana is a nonprofit organization empowering young women, girls and youth through practical skills development.'),
('footer.strapline', 'Empowering People | Building Communities | Creating Sustainable Opportunities'),
('site.logo_path', ''),
('site.logo_alt', 'RIT Foundation Ghana'),
('org.legal_name', 'RIT Foundation Ghana LBG'),
('org.goal', 'To provide skills acquisition and entrepreneurship development to the youth, mainly young women who are school dropouts, junior high school and senior high school graduates or uneducated in rural Ghana.'),
('org.objectives', CONCAT('Establish inclusive training programs in digital technology, vocational skills, and green technologies that empower individuals, with a focus on gender-responsive approaches, to lead in the digital economy.', CHAR(10), 'Develop a supportive ecosystem for business skill development and start-up incubation that addresses the unique needs of women and girls, fostering sustainable enterprises and economic independence.', CHAR(10), 'Foster leadership abilities, professional confidence, and essential soft skills through gender-responsive initiatives, enabling individuals to become innovative leaders and drivers of positive change.', CHAR(10), 'Design and implement practical, real-world projects that integrate technical expertise with entrepreneurial thinking, ensuring a gender-responsive approach to sustainability and innovation.')),
('org.beliefs', CONCAT('Advancing education for women and girls.', CHAR(10), 'Empowering women to build sustainable businesses.', CHAR(10), 'Promoting financial independence for women and youth.', CHAR(10), 'Encouraging skills acquisition and entrepreneurship.', CHAR(10), 'Integrating green skills and innovative practices.', CHAR(10), 'Advocating sustainability and self-reliance.', CHAR(10), 'Respecting and embracing community cultural values.')),
('about.history_text', CONCAT('RIT Foundation Ghana was established in 2020 and officially registered in 2021 as a non-governmental organization committed to empowering young women, girls, and the broader youth population through gender-responsive vocational, technical, and Information Technology training.', CHAR(10), CHAR(10), 'During the COVID-19 pandemic, the Foundation provided training to both young women and men in various vocational fields, including electricals, fashion design, auto-electricals, and painting and decoration. With initiatives such as the Teens in Tech project, the Foundation continues to bridge the gap between education and employability, fostering self-reliance, sustainable development, and innovation across rural Ghana.')),
('contact.postal_address', 'P. O. Box SG 90, Sogakope, Volta Region, Ghana');
