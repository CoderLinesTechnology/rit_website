<?php

declare(strict_types=1);

/*
 * Every editable site setting: where it appears in the admin "Website content"
 * screen, who may change it, its input type and its default value.
 * A value saved by an administrator (table site_settings) always wins over the
 * default below; deleting the row restores the default.
 *
 * Types: text (one line), textarea (plain text), markup (safe formatted text),
 * lines (one item per line), email, url, tel, bool, image (uploaded file path).
 * 'verify' => true marks facts that must be confirmed before they are published;
 * the dashboard lists them while they are empty.
 */

$hero = static function (string $page, string $label, string $heading, string $text, string $eyebrow = '', string $tagline = ''): array {
    $fields = [
        "{$page}.hero_eyebrow" => ['group' => 'pages', 'section' => $label, 'label' => 'Small heading above the title', 'type' => 'text', 'max' => 80, 'default' => $eyebrow],
        "{$page}.hero_heading" => ['group' => 'pages', 'section' => $label, 'label' => 'Page title', 'type' => 'text', 'max' => 120, 'required' => true, 'default' => $heading],
        "{$page}.hero_tagline" => ['group' => 'pages', 'section' => $label, 'label' => 'Tagline (optional)', 'type' => 'text', 'max' => 120, 'default' => $tagline],
        "{$page}.hero_text" => ['group' => 'pages', 'section' => $label, 'label' => 'Introduction', 'type' => 'textarea', 'max' => 400, 'default' => $text],
        "{$page}.hero_image" => ['group' => 'pages', 'section' => $label, 'label' => 'Header photo', 'type' => 'image', 'alt' => "{$page}.hero_image_alt", 'default' => ''],
        "{$page}.hero_image_alt" => ['group' => 'pages', 'section' => $label, 'label' => 'Header photo description (alt text)', 'type' => 'text', 'max' => 200, 'default' => ''],
    ];
    return $fields;
};

$privacyDefault = <<<'TXT'
## Who we are

RIT Foundation Ghana ("the Foundation", "we") is a nonprofit organization in Ghana. We are responsible for the personal information collected through this website. You can contact us about privacy at the email address shown on our Contact page.

## What we collect and why

We only collect the information you choose to send us through our forms:

- **Training applications:** your name, email address, telephone number, age range, region, preferred program, background, reason for applying, availability and any document you choose to upload. For applicants under 18, we also ask for a parent or guardian's name, telephone number and consent. We use this to assess your application and contact you about it.
- **Volunteer and partnership enquiries:** your contact details, organization (for partners), interests and message, so we can respond to you.
- **Contact messages:** your name, email address, optional telephone number and message, so we can reply.
- **Donations:** when online donations are available, a payment provider processes your payment. We keep the amount, a transaction reference, its status and the name and email you give us. We never see or store your card or mobile money PIN details.

Submitting an application does not guarantee a place on a program.

## Who can see your information

Only authorised Foundation staff and volunteers who need it to handle your submission can see it. Our website hosting provider and our email provider process data on our behalf to run this website. We do not sell your information or use it for advertising.

## How long we keep it

We keep submissions for up to 24 months after we receive them, unless a longer period is needed for a program you take part in or required by law. We then delete them.

## Cookies

This website uses one essential cookie on pages with forms and in the staff area, to keep forms secure and staff signed in. We do not use advertising or analytics cookies.

## Your rights

Under the Data Protection Act, 2012 (Act 843) you can ask to see the personal information we hold about you, ask us to correct or delete it, and object to how we use it. Contact us using the details on our Contact page. If you are not satisfied with our response, you can complain to the Data Protection Commission of Ghana.

## Security

We protect your information with access controls, encrypted connections (HTTPS) and limited staff access.

## Changes

We may update this notice. The current version is always published on this page.
TXT;

$termsDefault = <<<'TXT'
## About these terms

These terms apply to your use of the RIT Foundation Ghana website. By using the website you agree to them. If you do not agree, please do not use the website.

## Using the website

You may browse the website and share links to its pages. Please do not misuse the website, for example by trying to gain unauthorised access, sending automated or abusive submissions, or uploading files that contain malicious code.

## Applications and enquiries

Submitting a training application, volunteer application, partnership enquiry or message does not create an agreement and does not guarantee a place on a program or any other outcome. We will review submissions and contact you where appropriate. Please make sure the information you send is accurate.

## Donations

When online donations are available, payments are processed by a third-party payment provider under its own terms. A donation is only confirmed once the payment provider has verified it.

## Content and photographs

The text, logo and photographs on this website belong to RIT Foundation Ghana or are used with permission. Please ask us before reusing them. If you appear in a photograph on this website and would like it removed, contact us and we will review your request promptly.

## Links to other websites

The website links to social media and other websites that we do not control. We are not responsible for their content or how they handle your information.

## Availability and changes

We try to keep the information on this website accurate and up to date, but we cannot guarantee that it is always complete or available. We may update these terms from time to time; the current version is always published on this page.

## Governing law

These terms are governed by the laws of the Republic of Ghana.

## Contact

Questions about these terms can be sent to us using the details on our Contact page.
TXT;

return [
    'groups' => [
        'organization' => ['label' => 'Organization', 'permission' => 'settings.content'],
        'homepage' => ['label' => 'Homepage', 'permission' => 'settings.content'],
        'pages' => ['label' => 'Page headers & text', 'permission' => 'settings.content'],
        'contact' => ['label' => 'Contact details', 'permission' => 'settings.content'],
        'branding' => ['label' => 'Logo & shared images', 'permission' => 'settings.content'],
        'applications' => ['label' => 'Training applications', 'permission' => 'settings.content'],
        'donations' => ['label' => 'Donations', 'permission' => 'settings.system'],
        'notifications' => ['label' => 'Email notifications', 'permission' => 'settings.system'],
        'legal' => ['label' => 'Privacy & terms', 'permission' => 'settings.system'],
    ],

    'fields' => array_merge(
        [
            // Organization
            'org.name' => ['group' => 'organization', 'label' => 'Organization name', 'type' => 'text', 'max' => 120, 'required' => true, 'default' => 'RIT Foundation Ghana'],
            'org.motto' => ['group' => 'organization', 'label' => 'Motto (shown under the text logo)', 'type' => 'text', 'max' => 80, 'default' => 'Arte Divitiis sit'],
            'org.tagline' => ['group' => 'organization', 'label' => 'Short description (search engines and sharing)', 'type' => 'text', 'max' => 160, 'default' => 'Empowering young women, girls and youth through practical skills'],
            'org.legal_name' => ['group' => 'organization', 'label' => 'Registered (legal) name', 'type' => 'text', 'max' => 160, 'default' => 'RIT Foundation Ghana LBG'],
            'org.goal' => ['group' => 'organization', 'label' => 'Goal', 'type' => 'textarea', 'max' => 800, 'default' => 'To provide skills acquisition and entrepreneurship development to the youth, mainly young women who are school dropouts, junior high school and senior high school graduates or uneducated in rural Ghana.'],
            'org.vision' => ['group' => 'organization', 'label' => 'Vision', 'type' => 'textarea', 'max' => 800, 'verify' => true, 'default' => "To be West Africa's premier platform advancing gender responsive empowerment through innovative vocational and technical education, entrepreneurship, and sustainable development, creating pathways for their economic independence and leadership in the digital economy."],
            'org.mission' => ['group' => 'organization', 'label' => 'Mission', 'type' => 'textarea', 'max' => 800, 'verify' => true, 'default' => 'To deliver gender responsive transformative education and skill development across West Africa through cutting-edge technical training, entrepreneurial development, and sustainability practices, enabling them to become innovative leaders and architects of positive change in their communities and the region.'],
            'org.values' => ['group' => 'organization', 'label' => 'Values and principles (one per line)', 'type' => 'lines', 'max' => 600, 'verify' => true, 'default' => "Passion\nDiscipline\nPerseverance\nCreativity\nTime Consciousness\nDiligence\nExcellence\nLeadership\nHard Work"],
            'org.objectives' => ['group' => 'organization', 'label' => 'Objectives (one per line)', 'type' => 'lines', 'max' => 3000, 'default' => "Establish inclusive training programs in digital technology, vocational skills, and green technologies that empower individuals, with a focus on gender-responsive approaches, to lead in the digital economy.\nDevelop a supportive ecosystem for business skill development and start-up incubation that addresses the unique needs of women and girls, fostering sustainable enterprises and economic independence.\nFoster leadership abilities, professional confidence, and essential soft skills through gender-responsive initiatives, enabling individuals to become innovative leaders and drivers of positive change.\nDesign and implement practical, real-world projects that integrate technical expertise with entrepreneurial thinking, ensuring a gender-responsive approach to sustainability and innovation."],
            'org.beliefs' => ['group' => 'organization', 'label' => 'Organizational beliefs (one per line)', 'type' => 'lines', 'max' => 2000, 'default' => "Advancing education for women and girls.\nEmpowering women to build sustainable businesses.\nPromoting financial independence for women and youth.\nEncouraging skills acquisition and entrepreneurship.\nIntegrating green skills and innovative practices.\nAdvocating sustainability and self-reliance.\nRespecting and embracing community cultural values."],
            'org.overview' => ['group' => 'organization', 'label' => 'Organization overview', 'type' => 'markup', 'max' => 4000, 'default' => 'RIT Foundation Ghana empowers young women, girls, and youth through technical, vocational, digital, entrepreneurial, and practical skills development.'],
            'org.established_year' => ['group' => 'organization', 'label' => 'Year established', 'type' => 'text', 'max' => 20, 'verify' => true, 'default' => '2020'],
            'org.registration_details' => ['group' => 'organization', 'label' => 'Registration details (e.g. registration number and year)', 'type' => 'text', 'max' => 200, 'verify' => true, 'default' => 'Registered in 2021 under the Companies Act, 2019 (Act 992). Registration No. CG173310421.'],

            // Homepage
            'home.hero_eyebrow' => ['group' => 'homepage', 'section' => 'Hero', 'label' => 'Small heading above the title', 'type' => 'text', 'max' => 80, 'default' => 'Empower • Skill • Transform'],
            'home.hero_heading' => ['group' => 'homepage', 'section' => 'Hero', 'label' => 'Headline', 'type' => 'text', 'max' => 120, 'required' => true, 'default' => 'Equipping Young Women, Girls and Youth for a Brighter Future'],
            'home.hero_text' => ['group' => 'homepage', 'section' => 'Hero', 'label' => 'Supporting text', 'type' => 'textarea', 'max' => 400, 'default' => 'RIT Foundation Ghana provides technical, vocational, digital and entrepreneurial skills development to help young people discover their talents, build confidence and create sustainable opportunities.'],
            'home.hero_script' => ['group' => 'homepage', 'section' => 'Hero', 'label' => 'Handwritten phrase on photos', 'type' => 'text', 'max' => 60, 'default' => 'Skills Create Opportunities'],
            'home.hero_image' => ['group' => 'homepage', 'section' => 'Hero', 'label' => 'Hero photo', 'type' => 'image', 'alt' => 'home.hero_image_alt', 'default' => ''],
            'home.hero_image_alt' => ['group' => 'homepage', 'section' => 'Hero', 'label' => 'Hero photo description (alt text)', 'type' => 'text', 'max' => 200, 'default' => ''],
            'home.intro_eyebrow' => ['group' => 'homepage', 'section' => 'About strip', 'label' => 'Small heading', 'type' => 'text', 'max' => 80, 'default' => 'About us'],
            'home.intro_heading' => ['group' => 'homepage', 'section' => 'About strip', 'label' => 'Heading', 'type' => 'text', 'max' => 120, 'default' => 'Who We Are'],
            'home.intro_text' => ['group' => 'homepage', 'section' => 'About strip', 'label' => 'Text', 'type' => 'markup', 'max' => 1500, 'default' => 'RIT Foundation Ghana is a nonprofit organization committed to empowering young women, girls and youth through technical, vocational, digital and entrepreneurial skills development. To date, we have reached approximately 576 young women, girls and youth through training programmes, workshops, exhibitions and skills development initiatives.'],
            'home.intro_image' => ['group' => 'homepage', 'section' => 'About strip', 'label' => 'Photo', 'type' => 'image', 'alt' => 'home.intro_image_alt', 'default' => ''],
            'home.intro_image_alt' => ['group' => 'homepage', 'section' => 'About strip', 'label' => 'Photo description (alt text)', 'type' => 'text', 'max' => 200, 'default' => ''],
            'home.impact_eyebrow' => ['group' => 'homepage', 'section' => 'Impact band', 'label' => 'Small heading', 'type' => 'text', 'max' => 80, 'default' => 'Our impact'],
            'home.impact_heading' => ['group' => 'homepage', 'section' => 'Impact band', 'label' => 'Heading', 'type' => 'text', 'max' => 120, 'default' => 'Creating Real Change'],
            'home.impact_text' => ['group' => 'homepage', 'section' => 'Impact band', 'label' => 'Text', 'type' => 'textarea', 'max' => 400, 'default' => 'Through our programs and initiatives, we have empowered approximately 576 young women, girls and youth in Ghana.'],
            'home.programs_eyebrow' => ['group' => 'homepage', 'section' => 'Programs strip', 'label' => 'Small heading', 'type' => 'text', 'max' => 80, 'default' => 'Our programs'],
            'home.programs_heading' => ['group' => 'homepage', 'section' => 'Programs strip', 'label' => 'Heading', 'type' => 'text', 'max' => 120, 'default' => 'Skills for Today. Opportunities for Tomorrow.'],
            'home.gallery_eyebrow' => ['group' => 'homepage', 'section' => 'Gallery block', 'label' => 'Small heading', 'type' => 'text', 'max' => 80, 'default' => 'Gallery & events'],
            'home.gallery_heading' => ['group' => 'homepage', 'section' => 'Gallery block', 'label' => 'Heading', 'type' => 'text', 'max' => 120, 'default' => 'Moments That Inspire'],
            'home.gallery_text' => ['group' => 'homepage', 'section' => 'Gallery block', 'label' => 'Text', 'type' => 'textarea', 'max' => 300, 'default' => 'Explore photos from our training sessions, exhibitions, workshops and the Tech Skills Challenge.'],
            'home.gallery_image' => ['group' => 'homepage', 'section' => 'Gallery block', 'label' => 'Photo', 'type' => 'image', 'alt' => 'home.gallery_image_alt', 'default' => ''],
            'home.gallery_image_alt' => ['group' => 'homepage', 'section' => 'Gallery block', 'label' => 'Photo description (alt text)', 'type' => 'text', 'max' => 200, 'default' => ''],
            'home.news_heading' => ['group' => 'homepage', 'section' => 'News', 'label' => 'Heading', 'type' => 'text', 'max' => 120, 'default' => 'Latest News & Success Stories'],
            'home.involve_heading' => ['group' => 'homepage', 'section' => 'Get involved band', 'label' => 'Heading', 'type' => 'text', 'max' => 120, 'default' => 'Get Involved'],
            'home.involve_text' => ['group' => 'homepage', 'section' => 'Get involved band', 'label' => 'Text', 'type' => 'textarea', 'max' => 300, 'default' => 'Partner with us, volunteer, support our programs or make a donation. Together, we can create more opportunities.'],

            // Page-specific body text
            'programs.intro_heading' => ['group' => 'pages', 'section' => 'Programs page', 'label' => 'Section heading', 'type' => 'text', 'max' => 120, 'default' => 'Explore Our Program Areas'],
            'programs.intro_text' => ['group' => 'pages', 'section' => 'Programs page', 'label' => 'Section text', 'type' => 'textarea', 'max' => 400, 'default' => 'We offer a range of skills development programs designed to empower young people, build confidence and open doors to employment, entrepreneurship and leadership.'],
            'programs.cta_heading' => ['group' => 'pages', 'section' => 'Programs page', 'label' => 'Call-to-action heading', 'type' => 'text', 'max' => 120, 'default' => 'Ready to Build Your Future?'],
            'programs.cta_text' => ['group' => 'pages', 'section' => 'Programs page', 'label' => 'Call-to-action text', 'type' => 'textarea', 'max' => 300, 'default' => 'Apply today for our training programs and take the next step towards your goals.'],

            'about.story_eyebrow' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Story: small heading', 'type' => 'text', 'max' => 80, 'default' => 'Our story'],
            'about.story_heading' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Story: heading', 'type' => 'text', 'max' => 120, 'default' => 'Who We Are'],
            'about.story_text' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Story: text', 'type' => 'markup', 'max' => 5000, 'default' => "RIT Foundation Ghana is a nonprofit organization dedicated to empowering young women, girls and youth through technical, vocational, digital, entrepreneurial and practical skills development.\n\nWe believe that every young person has unique talents and potential. Through hands-on training and opportunities, we help them build confidence, gain relevant skills, explore employment and entrepreneurship, and work towards financial independence.\n\nOur work supports education, innovation, leadership, self-reliance and stronger communities."],
            'about.story_image' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Story: photo', 'type' => 'image', 'alt' => 'about.story_image_alt', 'default' => ''],
            'about.story_image_alt' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Story: photo description', 'type' => 'text', 'max' => 200, 'default' => ''],
            'about.history_heading' => ['group' => 'pages', 'section' => 'About page', 'label' => 'History: heading', 'type' => 'text', 'max' => 120, 'default' => 'Our Journey'],
            'about.history_text' => ['group' => 'pages', 'section' => 'About page', 'label' => 'History: text', 'type' => 'markup', 'max' => 3000, 'verify' => true, 'help' => 'Only include dates and facts that have been verified. Milestones are managed under Impact › Team & milestones.', 'default' => "RIT Foundation Ghana was established in 2020 and officially registered in 2021 as a non-governmental organization committed to empowering young women, girls, and the broader youth population through gender-responsive vocational, technical, and Information Technology training.\n\nDuring the COVID-19 pandemic, the Foundation provided training to both young women and men in various vocational fields, including electricals, fashion design, auto-electricals, and painting and decoration. With initiatives such as the Teens in Tech project, the Foundation continues to bridge the gap between education and employability, fostering self-reliance, sustainable development, and innovation across rural Ghana."],
            'about.impact_text' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Impact box text', 'type' => 'textarea', 'max' => 300, 'default' => 'We are proud of the lives we have touched and remain committed to reaching even more young people in the years ahead.'],
            'about.team_heading' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Team: heading', 'type' => 'text', 'max' => 120, 'default' => 'Leadership & Team'],
            'about.team_text' => ['group' => 'pages', 'section' => 'About page', 'label' => 'Team: text', 'type' => 'textarea', 'max' => 600, 'default' => 'Meet the people leading RIT Foundation Ghana.'],

            'impact.story_eyebrow' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Story: small heading', 'type' => 'text', 'max' => 80, 'default' => 'The impact'],
            'impact.story_heading' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Story: heading', 'type' => 'text', 'max' => 120, 'default' => 'Changing Lives Through Skills'],
            'impact.story_text' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Story: text', 'type' => 'markup', 'max' => 3000, 'default' => "RIT Foundation Ghana's impact goes beyond training: it builds confidence, creates opportunities and strengthens communities. We equip young people with practical skills, promote entrepreneurship, and support them as they take the next step toward a brighter future."],
            'impact.story_image' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Story: photo', 'type' => 'image', 'alt' => 'impact.story_image_alt', 'default' => ''],
            'impact.story_image_alt' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Story: photo description', 'type' => 'text', 'max' => 200, 'default' => ''],
            'impact.areas' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Key areas of impact (one per line: Title | description)', 'type' => 'lines', 'max' => 1500, 'default' => "Education & Skills Development | Practical, hands-on training for real-world success.\nEntrepreneurship | Supporting young people to start and grow their own businesses.\nCommunity Development | Stronger, more resilient communities through sustainable initiatives.\nGender Equality | Creating equal opportunities for all young people."],
            'impact.stories_heading' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Success stories: heading', 'type' => 'text', 'max' => 120, 'default' => 'Success Stories'],
            'impact.stories_text' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Success stories: text', 'type' => 'textarea', 'max' => 600, 'default' => 'Meet some of the young people who have gained new skills through RIT Foundation Ghana programs. Their stories show what is possible.'],
            'impact.stories_image' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Success stories: photo', 'type' => 'image', 'alt' => 'impact.stories_image_alt', 'default' => ''],
            'impact.stories_image_alt' => ['group' => 'pages', 'section' => 'Impact page', 'label' => 'Success stories: photo description', 'type' => 'text', 'max' => 200, 'default' => ''],

            'get-involved.volunteer_heading' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Volunteer: heading', 'type' => 'text', 'max' => 120, 'default' => 'Share Your Time & Skills'],
            'get-involved.volunteer_text' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Volunteer: text', 'type' => 'textarea', 'max' => 600, 'default' => 'Your time, skills and experience can make a real difference. We welcome individuals who are passionate about empowering young people and supporting community development.'],
            'get-involved.volunteer_ways' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Volunteer: ways to volunteer (one per line)', 'type' => 'lines', 'max' => 1000, 'default' => "Mentor and coach trainees\nSupport training workshops and events\nShare your professional skills and expertise\nHelp with community outreach and awareness programs"],
            'get-involved.volunteer_why' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Volunteer: why volunteer (one per line)', 'type' => 'lines', 'max' => 1000, 'default' => "Make a lasting impact\nBuild your skills and experience\nBe part of a supportive community\nHelp create opportunities for young people"],
            'get-involved.volunteer_quote' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Volunteer: short quote (optional)', 'type' => 'text', 'max' => 200, 'default' => 'Small actions, when multiplied by many people, can transform communities.'],
            'get-involved.volunteer_image' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Volunteer: photo', 'type' => 'image', 'alt' => 'get-involved.volunteer_image_alt', 'default' => ''],
            'get-involved.volunteer_image_alt' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Volunteer: photo description', 'type' => 'text', 'max' => 200, 'default' => ''],
            'get-involved.partner_heading' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Partnerships: heading', 'type' => 'text', 'max' => 120, 'default' => 'Build Stronger Together'],
            'get-involved.partner_text' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Partnerships: text', 'type' => 'textarea', 'max' => 600, 'default' => 'We welcome partnerships with organizations, businesses, educational institutions and individuals who share our vision of empowering young people through skills and opportunity.'],
            'get-involved.partner_ways' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Partnerships: opportunities (one per line)', 'type' => 'lines', 'max' => 1000, 'default' => "Training and capacity building\nResource and equipment support\nInternship and job placement opportunities\nJoint projects and community initiatives\nKnowledge and expertise sharing"],
            'get-involved.partner_image' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Partnerships: photo', 'type' => 'image', 'alt' => 'get-involved.partner_image_alt', 'default' => ''],
            'get-involved.partner_image_alt' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Partnerships: photo description', 'type' => 'text', 'max' => 200, 'default' => ''],
            'get-involved.support_heading' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Support: heading', 'type' => 'text', 'max' => 120, 'default' => 'Help Us Create Lasting Change'],
            'get-involved.support_text' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Support: text', 'type' => 'textarea', 'max' => 600, 'default' => 'Your support helps us provide training, resources and opportunities for young women, girls and youth. Every contribution, big or small, makes a difference.'],
            'get-involved.support_ways' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Support: ways to support (one per line)', 'type' => 'lines', 'max' => 1000, 'default' => "Make a donation\nSponsor a training program\nProvide materials or equipment\nSupport our events and initiatives"],
            'get-involved.support_image' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Support: photo', 'type' => 'image', 'alt' => 'get-involved.support_image_alt', 'default' => ''],
            'get-involved.support_image_alt' => ['group' => 'pages', 'section' => 'Get Involved page', 'label' => 'Support: photo description', 'type' => 'text', 'max' => 200, 'default' => ''],

            'apply.intro_text' => ['group' => 'pages', 'section' => 'Apply page', 'label' => 'Form introduction', 'type' => 'textarea', 'max' => 600, 'default' => 'Fill in the form below to apply for our available training programs. Make sure all details are accurate and complete. You will receive an application reference number after you submit.'],
            'apply.notice' => ['group' => 'pages', 'section' => 'Apply page', 'label' => 'Notice above the form', 'type' => 'textarea', 'max' => 400, 'default' => 'Fields marked with * are required. The information you provide will be kept confidential and used only to process your application.'],
            'apply.sidebar_text' => ['group' => 'pages', 'section' => 'Apply page', 'label' => 'Sidebar text', 'type' => 'textarea', 'max' => 400, 'default' => 'We offer a range of skills development programs designed to empower young people, build confidence and open doors to employment, entrepreneurship and leadership.'],

            // Contact
            'contact.email' => ['group' => 'contact', 'label' => 'Email address', 'type' => 'email', 'max' => 190, 'verify' => true, 'default' => 'ritfoundationofficial@gmail.com'],
            'contact.phone' => ['group' => 'contact', 'label' => 'Telephone', 'type' => 'tel', 'max' => 30, 'verify' => true, 'default' => '+233 55 494 2195'],
            'contact.phone_secondary' => ['group' => 'contact', 'label' => 'Second telephone (optional)', 'type' => 'tel', 'max' => 30, 'default' => '+233 24 251 5127'],
            'contact.address' => ['group' => 'contact', 'label' => 'Office address', 'type' => 'textarea', 'max' => 300, 'verify' => true, 'default' => 'Sogasco Street, Sogakope, Volta Region, Ghana'],
            'contact.postal_address' => ['group' => 'contact', 'label' => 'Postal address', 'type' => 'text', 'max' => 200, 'default' => 'P. O. Box SG 90, Sogakope, Volta Region, Ghana'],
            'contact.office_hours' => ['group' => 'contact', 'label' => 'Office hours (optional)', 'type' => 'text', 'max' => 120, 'default' => ''],
            'contact.map_embed_url' => ['group' => 'contact', 'label' => 'Google Maps embed link (optional)', 'type' => 'url', 'max' => 500, 'help' => 'Only add a map once a full, verified office address is published. Use the "Embed a map" link from Google Maps (https://www.google.com/maps/embed?...). The map loads from Google, which is noted in the privacy notice.', 'default' => ''],

            // Branding and shared images
            'site.logo_path' => ['group' => 'branding', 'label' => 'Logo (PNG or WebP with a transparent background works best)', 'type' => 'image', 'alt' => 'site.logo_alt', 'default' => ''],
            'site.logo_alt' => ['group' => 'branding', 'label' => 'Logo description', 'type' => 'text', 'max' => 120, 'default' => 'RIT Foundation Ghana'],
            'site.cta_image' => ['group' => 'branding', 'label' => 'Photo for the call-to-action bands', 'type' => 'image', 'alt' => 'site.cta_image_alt', 'default' => ''],
            'site.cta_image_alt' => ['group' => 'branding', 'label' => 'Call-to-action photo description', 'type' => 'text', 'max' => 200, 'default' => ''],
            'site.share_image' => ['group' => 'branding', 'label' => 'Default image when pages are shared on social media', 'type' => 'image', 'default' => ''],
            'footer.text' => ['group' => 'branding', 'label' => 'Footer description', 'type' => 'textarea', 'max' => 300, 'default' => 'RIT Foundation Ghana is a nonprofit organization empowering young women, girls and youth through practical skills development.'],
            'footer.strapline' => ['group' => 'branding', 'label' => 'Footer strapline', 'type' => 'text', 'max' => 160, 'default' => 'Empowering People | Building Communities | Creating Sustainable Opportunities'],

            // Applications
            'applications.enabled' => ['group' => 'applications', 'label' => 'Accept training applications on the website', 'type' => 'bool', 'help' => 'Each program also has its own opening and closing dates.', 'default' => '1'],
            'applications.closed_message' => ['group' => 'applications', 'label' => 'Message shown when no applications are open', 'type' => 'textarea', 'max' => 400, 'default' => 'Applications are not open at the moment. Please check back soon or follow us on social media for announcements.'],
            'applications.allow_attachment' => ['group' => 'applications', 'label' => 'Allow an optional supporting document (PDF, JPG or PNG, up to 5 MB)', 'type' => 'bool', 'help' => 'Only keep this on if the documents are genuinely needed to assess applications.', 'default' => '1'],

            // Donations
            'donations.notice' => ['group' => 'donations', 'label' => 'Notice shown while online donations are unavailable', 'type' => 'textarea', 'max' => 400, 'default' => 'Online donations are not available yet. We are setting up a secure payment option.'],
            'donations.offline_instructions' => ['group' => 'donations', 'label' => 'Other ways to give (e.g. bank or mobile money details)', 'type' => 'markup', 'max' => 2000, 'verify' => true, 'help' => 'Only publish account details that the Foundation has confirmed.', 'default' => ''],

            // Notifications
            'notify.email' => ['group' => 'notifications', 'label' => 'Send staff notifications to', 'type' => 'email', 'max' => 190, 'help' => 'New applications and enquiries are announced here (without personal details). Leave blank to turn staff notifications off.', 'default' => 'ritfoundationofficial@gmail.com'],
            'notify.send_confirmations' => ['group' => 'notifications', 'label' => 'Email a confirmation with the reference number to people who submit a form', 'type' => 'bool', 'default' => '1'],

            // Legal
            'privacy.policy' => ['group' => 'legal', 'label' => 'Privacy notice', 'type' => 'markup', 'max' => 20000, 'default' => $privacyDefault],
            'privacy.reviewed' => ['group' => 'legal', 'label' => 'The privacy notice has been reviewed and approved by the Foundation', 'type' => 'bool', 'default' => '0'],
            'terms.content' => ['group' => 'legal', 'label' => 'Terms & conditions', 'type' => 'markup', 'max' => 20000, 'default' => $termsDefault],
            'terms.reviewed' => ['group' => 'legal', 'label' => 'The terms have been reviewed and approved by the Foundation', 'type' => 'bool', 'default' => '0'],
        ],
        $hero('about', 'About page', 'About Us', 'Empowering young women, girls and youth through skills development, education and opportunity.'),
        $hero('programs', 'Programs page', 'Skills for a Brighter Future', 'At RIT Foundation Ghana, we provide practical, hands-on training to help young women, girls and youth gain the skills, confidence and opportunities they need to build sustainable livelihoods and create lasting change.', 'Our programs'),
        $hero('impact', 'Impact page', 'Our Impact', 'Through practical skills training, RIT Foundation Ghana helps young women, girls and youth build confidence and create opportunities.', '', 'Real skills. Brighter futures.'),
        $hero('gallery', 'Gallery page', 'Moments That Inspire Change', 'Explore photos from our training programs, workshops, events and community activities. These moments capture the skills, confidence and opportunities we create together.', 'Our gallery'),
        $hero('news', 'News page', 'Latest News & Inspiring Stories', 'Stay updated with our latest activities, events and success stories. Discover how RIT Foundation Ghana is creating opportunities and transforming lives.', 'News & success stories'),
        $hero('get-involved', 'Get Involved page', 'Get Involved', 'Together, we can create more opportunities for young women, girls and youth. There are many ways to support RIT Foundation Ghana, whether through volunteering, partnerships or financial support.'),
        $hero('volunteer', 'Volunteer page', 'Volunteer With Us', 'Share your time, skills and experience to help young women, girls and youth build practical skills and confidence.'),
        $hero('partner', 'Partner page', 'Partner With Us', 'Work with RIT Foundation Ghana to expand skills and opportunities for young people.'),
        $hero('apply', 'Apply page', 'Training Applications', 'Take the next step towards your future! Apply for our training programs and gain practical skills, confidence and opportunities for a better tomorrow.'),
        $hero('contact', 'Contact page', 'Contact Us', 'Have a question about our programs, volunteering or partnerships? Send us a message and we will get back to you.'),
        $hero('donate', 'Donate page', 'Support Our Work', 'Your support helps more young women, girls and youth gain practical skills for work, enterprise and independence.'),
    ),
];
