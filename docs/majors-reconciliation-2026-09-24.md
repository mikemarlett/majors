# Majors: CMS pages vs the majors database (2026-09-24)

The `majors_academic_programs` / `majors_programs_content` tables were scraped from the CMS program pages on 2023-08-14. This compares them with what the CMS holds today under `www:/academics/majors/` (staging, via the MC API). Page sources are cached on the sandbox for the importer to come.

## Numbers

| | count |
|---|---|
| CMS program pages (`Details: …` titles) | 499 |
| other pages in the folder (listing pages, excluded) | 9: certificates, graduate, graduate_by_college, index, index_by_college, majors, majors_by_college, online, online_by_college |
| database content rows | 464 |
| matched by basename | 386 |
| CMS pages with no database row | 113 |
| database rows whose page no longer exists | 78 |
| matched pages edited in the CMS since the scrape | 370 of 386 |
| matched pages whose title changed | 20 |
| graduate pages in the CMS / graduate rows in the database | 157 / 149 |
| graduate pages with no database row | 42 |

CMS pages by kind (from the page title): Major 136, Minor 85, Master's 78, Graduate Certificate 74, Undergraduate Certificate 59, Bachelor's to Master's 24, Doctorate 14, LAS 5, Field Major 5, Postbaccalaureate 3, CHP 2, Ethnicity and Intersectional Studies 2, Winds and Percussion 1, CED 1, Bachelor's to Juris Doctorate 1, Undergraduate Emphasis 1, Practicum Placement 1, Post Master 1, Government and Social Studies (Secondary) 1, Field Major or Bachelor of General Studies 1, Design and Creative Industries 1, Environmental and Physical Sciences 1, Entry Level Program 1, Endorsement 1

## What this means

- **Almost every page has been edited since the scrape** (marketing has kept the CMS pages current), so the database content is two years stale for nearly all programs, not just the ones added or removed. A refresh has to re-read every page, not patch the differences.
- **Basenames are stable identifiers.** Pages carry a numeric program id in the slug (`…_bs_100`) for most degrees; certificates, minors and some newer pages have none. Renames keep the id, so the id is the key for matching old rows to renamed pages where it exists; the slug itself is the key otherwise.
- **The page structure is regular**: a Program Card snippet (kind, name, breadcrumb links, description, "Learn how…" buttons), a media block, teasers (Applied learning, Admission), Inside the Program, Curriculum / Careers teasers. That is exactly the shape of the content table, so a new importer can parse the PCF source rather than the rendered HTML.

## CMS pages with no database row (113)

- `accounting_information_systems_minor` — Details: Information Systems for Accountants, Minor, BUS (edited 5/22/25, 4:59 PM)
- `advanced_professional_teaching_learning_skills_certificate_graduate` — Details: Advanced Professional Teaching and Learning Skills, Graduate Certificate, CED (edited 6/29/26, 9:12 AM)
- `advanced_public_health_practice_certificate_undergraduate` — Details: Advanced Public Health Practice, Undergraduate Certificate, CHP (edited 8/27/25, 10:51 AM)
- `agile_product_management_certificate_graduate` — Details: Agile Product Management, Graduate Certificate, CID (edited 8/13/26, 3:11 PM)
- `american_sign_language_minor` — Details: American Sign Language, Minor, LAS (edited 5/22/25, 4:59 PM)
- `applied_behavior_analysis_med` — Details: Applied Behavior Analysis, Master's, CED (edited 8/12/26, 10:10 AM)
- `applied_engineering_civil_environmental_engineering_bs_108` — Details: Applied Engineering - Civil and Environmental Engineering, Major, ENG (edited 6/17/26, 1:25 PM)
- `applied_engineering_engineering_management_bs_110` — Details: Applied Engineering - Engineering Management, Major, ENG (edited 6/17/26, 1:34 PM)
- `applied_engineering_process_automation_bs_111` — Details: Applied Engineering - Process Automation, Major, ENG (edited 6/17/26, 1:34 PM)
- `arts_entrepreneurship_minor` — Details: Arts Entrepreneurship, Minor, CFA (edited 4/22/26, 10:57 AM)
- `associate_of_science_as` — Details: Associate of Science, LAS (edited 5/22/25, 4:59 PM)
- `athletic_training_accelerated_bachelors_to_masters` — Details: Athletic Training, Bachelor's to Master's, CHP (edited 8/13/26, 2:56 PM)
- `business_administration_accelerated_bachelors_to_masters` — Details: Business Administration, Bachelor's to Master's, BUS (edited 7/21/26, 6:54 PM)
- `business_administration_hospitality_bba` — Details: Hospitality, Major, BUS (edited 7/21/26, 10:56 AM)
- `business_administration_marketing_digital_bba` — Details: Marketing - Digital Marketing, Major, BUS (edited 4/29/26, 4:04 PM)
- `ceramics_minor` — Details: Ceramics, Minor, CFA (edited 4/22/26, 10:58 AM)
- `coaching_certificate_undergraduate` — Details: Coaching, Undergraduate Certificate, CED (edited 8/14/26, 8:49 AM)
- `commercial_real_estate_investment_development_certificate_graduate` — Details: Commercial Real Estate Investment and Development, Graduate Certificate, BUS (edited 8/15/25, 4:02 PM)
- `communication_communication_studies_emphasis_ba_214` — Details: Communication - Communication Studies, Major, LAS (edited 5/22/25, 4:59 PM)
- `community-based_aging_services_certificate_graduate` — Details: Community-Based Aging Services, Graduate Certificate, CHP (edited 8/13/26, 3:31 PM)
- `community_social_practices_minor` — Details: Community & Social Practices, Minor, CFA (edited 4/22/26, 10:59 AM)
- `computing_systems_certificate_graduate` — Details: Computing Systems, Graduate Certificate, ENG (edited 4/22/26, 11:06 AM)
- `control_systems_certificate_graduate` — Details: Control Systems, Graduate Certificate, ENG (edited 4/22/26, 11:07 AM)
- `crime_scene_investigation_certificate_undergraduate` — Details: Crime Scene Investigation, Undergraduate Certificate, LAS (edited 4/23/26, 1:00 PM)
- `criminal_intelligence_certificate_graduate` — Details: Criminal Intelligence, Graduate Certificate, LAS (edited 2/27/26, 10:12 AM)
- `criminal_justice_criminal_intelligence_bs` — Details: Criminal Justice - Criminal Intelligence, Major, LAS (edited 5/22/25, 4:59 PM)
- `criminal_justice_law_bs` — Details: Criminal Justice - Courts and Law, Major, LAS (edited 4/21/26, 5:04 PM)
- `cybersecurity_minor` — Details: Cybersecurity, Minor, ENG (edited 9/16/25, 5:26 PM)
- `dance_and_psychology_minor` — Details: Dance and Psychology, Minor, CFA (edited 4/21/26, 11:40 AM)
- `dance_education_minor` — Details: Dance Education, Minor, CFA (edited 4/21/26, 11:36 AM)
- `data-centric_modern_communications_certificate_graduate` — Details: Data-Centric Modern Communications, Graduate Certificate, ENG (edited 4/22/26, 11:11 AM)
- `drama_and_psychology_minor` — Details: Drama and Psychology, Minor, CFA (edited 4/21/26, 11:41 AM)
- `drawing_painting_minor` — Details: Drawing & Panting, Minor, CFA (edited 4/22/26, 11:12 AM)
- `education_behavioral_studies_clinical_mental_health_counselor_phd` — Details: Education and Behavioral Studies - Clinical Mental Health Counselor Education and Supervision, Doctorate, CED (edited 8/12/26, 10:52 AM)
- `education_behavioral_studies_ed_psych_phd` — Details: Education and Behavioral Studies - Educational Psychology, Doctorate, CED (edited 8/12/26, 10:51 AM)
- `education_middle_level_mathematics_apprentice_baed` — Details: Education - Mathematics (Middle) - Teacher Apprentice Program™, Major, CED (edited 8/14/26, 12:10 PM)
- `education_middle_level_science_apprentice_baed` — Details: Education - Science (Middle) - Teacher Apprentice Program™, Major, CED (edited 8/14/26, 12:09 PM)
- `educational_studies_minor` — Details: Educational Studies, Minor, CED (edited 6/29/26, 9:12 AM)
- `energy_engineering_certificate_graduate` — Details: Energy Engineering, Graduate Certificate, ENG (edited 4/22/26, 11:10 AM)
- `entrepreneurship_certificate_undergraduate` — Details: Entrepreneurship, Undergraduate Certificate, BUS (edited 7/21/26, 10:38 AM)
- `exercise_science_bs_77` — Details: Exercise Science, Major, CHP (edited 8/25/26, 4:26 PM)
- `exercise_science_ms_78` — Details: Exercise Science, Master's, CHP (edited 6/29/26, 8:49 AM)
- `exercise_science_strength_conditioning_concentration_bs` — Details: Exercise Science - Strength and Conditioning, Major, CHP (edited 8/25/26, 4:25 PM)
- `facilities_management_certificate_undergraduate` — Details: Facilities Management, Undergraduate Certificate, ENG (edited 3/27/24, 1:14 PM)
- `financial_services_certificate_undergraduate` — Details: Financial Services, Undergraduate Certificate, BUS (edited 1/23/25, 2:51 PM)
- `fitness_certificate_undergraduate` — Details: Fitness, Undergraduate Certificate, CHP (edited 6/29/26, 8:50 AM)
- `forensic_biology_ms` — Details: Forensic Biology, Master's, LAS (edited 6/2/26, 2:50 PM)
- `forensic_firearms_ms` — Details: Forensic Firearms, Master's, LAS (edited 6/2/26, 2:50 PM)
- `foundational_public_health_practice_certificate_undergraduate` — Details: Foundational Public Health Practice, Undergraduate Certificate, CHP (edited 8/27/25, 10:53 AM)
- `health_administration_mha_to_ma_aging_studies` — Details: Master of Health Administration (MHA) to MA in Aging Studies, CHP (edited 6/2/26, 2:50 PM)
- `health_humanities_certificate_undergraduate` — Details: Health Humanities, Undergraduate Certificate, LAS (edited 8/15/25, 4:57 PM)
- `healthcare_leadership_certificate_graduate_362` — Details: Healthcare Leadership, Graduate Certificate, CHP (edited 8/13/26, 3:32 PM)
- `healthcare_management_certificate_undergraduate` — Details: Healthcare Management, Undergraduate Certificate, CHP (edited 8/27/25, 10:56 AM)
- `healthcare_systems_certificate_undergraduate` — Details: Healthcare Systems, Undergraduate Certificate, CHP (edited 4/23/26, 12:56 PM)
- `hospitality_certificate_undergraduate` — Details: Hospitality, Undergraduate Certificate, BUS (edited 7/21/26, 11:06 AM)
- `hospitality_minor` — Details: Hospitality, Minor, BUS (edited 7/21/26, 10:59 AM)
- `human_anatomy_certificate_undergraduate` — Details: Human Anatomy, Undergraduate Certificate, CHP (edited 8/22/25, 12:24 PM)
- `innovation_design_dual_accelerated_bachelors_to_masters` — Details: Innovation Design, Bachelor's to Master's, CID (edited 5/22/25, 4:59 PM)
- `jazz_studies_bm_139` — Details: Jazz Studies, Major, CFA (edited 4/21/26, 4:24 PM)
- `latin_american_latino_studies_certificate_undergraduate` — Details: Latin American and Latino Studies, Undergraduate Certificate, LAS (edited 3/17/25, 10:59 AM)
- `law_enforcement_local_government_administration_certificate_graduate` — Details: Law Enforcement and Local Government Administration, Graduate Certificate, LAS (edited 8/14/26, 9:20 AM)
- `leadership_character_development_minor` — Details: Leadership and Character Development, Minor, CED (edited 6/29/26, 9:12 AM)
- `learning_and_instructional_design_accelerated_bachelors_to_masters` — Details: Learning and Instructional Design, Bachelor's to Master's, CED (edited 6/29/26, 9:12 AM)
- `mathematical_data_science_ms` — Details: Mathematical Data Science, Master's, LAS (edited 6/2/26, 2:50 PM)
- `music_composition_certificate_graduate` — Details: Music Composition, Graduate Certificate, CFA (edited 4/23/26, 12:59 PM)
- `music_education_mme` — Details: Music Education, Master's, CFA (edited 8/10/26, 1:29 PM)
- `music_music_industry_studies_emphasis_ba` — Details: Music - Music Industry Studies, Major, CFA (edited 4/23/26, 12:42 PM)
- `music_performance_chamber_music_concentration_mm_296` — Details: Music - Performance - Chamber Music, Master's, CFA (edited 6/2/26, 2:50 PM)
- `music_performance_commercial_music_emphasis_bm` — Details: Performance - Commercial Music, Major, CFA (edited 4/23/26, 12:43 PM)
- `music_performance_opera_concentration_mm_298` — Details: Music - Opera Performance, Master's, CFA (edited 6/2/26, 2:50 PM)
- `music_performance_piano_concentration_mm_284` — Details: Music - Performance - Piano , Master's, CFA (edited 6/2/26, 2:50 PM)
- `music_performance_strings_winds_and_percussion_concentration_mm_285` — Details: Music - Performance - Strings, Winds and Percussion , Master's, CFA (edited 6/2/26, 2:50 PM)
- `music_performance_voice_concentration_mm_293` — Details: Music - Performance - Voice , Master's, CFA (edited 6/2/26, 2:50 PM)
- `music_theory_certificate_graduate` — Details: Music Theory, Graduate Certificate, CFA (edited 4/23/26, 12:58 PM)
- `musicology_certificate_graduate` — Details: Musicology, Graduate Certificate, CFA (edited 2/26/26, 3:20 PM)
- `nursing_bsn_179` — Details: Nursing - Bachelor of Science in Nursing, Major, CHP (edited 8/13/26, 2:52 PM)
- `nursing_nurse_executive_healthcare_leadership_msn_317` — Details: Nursing - Nurse Executive and Healthcare Leadership, Master's, CHP (edited 6/2/26, 2:50 PM)
- `organizational_leadership_certificate_graduate` — Details: Organizational Leadership, Graduate Certificate, CED (edited 6/29/26, 9:12 AM)
- `organizational_learning_certificate_graduate` — Details: Organizational Learning, Graduate Certificate, CED (edited 6/29/26, 9:12 AM)
- `outdoor_leadership_education_minor` — Details: Outdoor Leadership and Education, Minor, CED (edited 6/29/26, 9:12 AM)
- `performing_arts_acting_bfa_162` — Details: Acting, Major, CFA (edited 4/21/26, 4:09 PM)
- `performing_arts_business_minor` — Details: Performing Arts and Business, Minor, CFA (edited 4/21/26, 11:44 AM)
- `performing_arts_marketing_minor` — Details: Performing Arts Marketing, Minor, CFA (edited 4/21/26, 11:44 AM)
- `photo_media_minor` — Details: Photo Media, Minor, CFA (edited 4/23/26, 8:58 AM)
- `physical_education_prek_12_baed_79` — Details: Physical Education (PreK-12), Major, CED (edited 6/29/26, 9:12 AM)
- `physician_associate_mpa_311` — Details: Physician Associate, Master's, CHP (edited 8/18/26, 12:02 PM)
- `population_health_certificate_undergraduate` — Details: Population Health, Undergraduate Certificate, CHP (edited 4/23/26, 12:45 PM)
- `power_system_operations_certificate_graduate` — Details: Power System Operations, Graduate Certificate, ENG (edited 4/22/26, 11:09 AM)
- `power_system_planning_certificate_graduate` — Details: Power System Planning, Graduate Certificate, ENG (edited 4/22/26, 11:08 AM)
- `pre-genetic_counseling_certificate_undergraduate` — Details: Pre-Genetic Counseling, Undergraduate Certificate, LAS (edited 12/20/24, 1:41 PM)
- `printmaking_minor` — Details: Printmaking, Minor, CFA (edited 4/23/26, 12:40 PM)
- `professional_writing_editing_minor` — Details: Professional Writing and Editing, Minor, LAS (edited 5/22/25, 5:00 PM)
- `reading_specialist_structured_literacy_certificate_graduate_92` — Details: Reading Specialist and Structured Literacy, Graduate Certificate, CED (edited 6/29/26, 9:12 AM)
- `real_estate_certificate_undergraduate` — Details: Real Estate, Undergraduate Certificate, BUS (edited 8/15/25, 4:05 PM)
- `renewable_energy_storage_certificate_graduate` — Details: Renewable Energy and Storage, Graduate Certificate, ENG (edited 4/22/26, 11:09 AM)
- `school_psychology_eds_88` — Details: School Psychology - Specialist, CED (edited 8/12/26, 10:28 AM)
- `sculpture_minor` — Details: Sculpture, Minor, CFA (edited 6/17/26, 1:27 PM)
- `senior_living_certificate_graduate` — Details: Senior Living, Graduate Certificate, CHP (edited 8/13/26, 3:33 PM)
- `special_education_high_incidence_accelerated_bachelors_to_masters` — Details: Special Education - High Incidence, Bachelor's to Master's, CED (edited 8/12/26, 4:16 PM)
- `special_education_high_incidence_certificate_graduate` — Details: Special Education - High Incidence, Graduate Certificate, CED (edited 6/29/26, 9:12 AM)
- `special_education_low_incidence_accelerated_bachelors_to_masters` — Details: Special Education - Low Incidence, Bachelor's to Master's, CED (edited 8/12/26, 4:17 PM)
- `special_education_low_incidence_alternative_certification_med` — Details: Special Education - Low Incidence Alternative Certification, Master's, CED (edited 6/29/26, 9:12 AM)
- `special_education_low_incidence_certificate_graduate` — Details: Special Education - Low Incidence, Graduate Certificate, CED (edited 6/29/26, 9:12 AM)
- `special_music_education_bme_138` — Details: Special Music Education, Major, CFA (edited 5/22/25, 5:00 PM)
- `student_affairs_practitioner_wellness_effectiveness_certificate_graduate_131` — Details: Student Affairs Practitioner Wellness and Effectiveness, Graduate Certificate, CED (edited 8/11/26, 4:33 PM)
- `sustainable_energy_systems_certificate_undergraduate` — Details: Sustainable Energy Systems, Undergraduate Certificate, ENG (edited 3/27/24, 1:15 PM)
- `sustainable_water_resources_certificate_undergraduate` — Details: Sustainable Water Resources, Undergraduate Certificate, ENG (edited 3/27/24, 1:15 PM)
- `teaching_excellence_leadership_certificate_graduate` — Details: Teaching Excellence and Leadership, Graduate Certificate, CED (edited 6/29/26, 9:12 AM)
- `teaching_higher_education_certificate_graduate_263` — Details: Teaching in Higher Education, Graduate Certificate, ENG/CED (edited 6/29/26, 9:12 AM)
- `text_technologies_minor` — Details: Text Technologies, Minor, LAS (edited 5/22/25, 5:00 PM)
- `transportation_electrification_certificate_graduate` — Details: Transportation Electrification, Graduate Certificate, ENG (edited 4/16/24, 7:29 PM)
- `urban_policy_innovation_certificate_graduate` — Details: Urban Policy and Innovation, Graduate Certificate, LAS (edited 8/14/26, 9:19 AM)
- `weight_training_certificate_undergraduate` — Details: Weight Training, Undergraduate Certificate, CHP (edited 6/29/26, 8:50 AM)

## Database rows whose page is gone (78)

Likely renames (same numeric id, or strongly overlapping slug) are marked; the rest look retired.

- `accountancy_accounting_information_systems_concentration_macc_33` — Accountancy - Accounting Information Systems
- `aging_studies_graduate_emphasis_307` — Aging Studies
- `anthropology_field_major_187` — Anthropology
- `art_studio_art_electronic_media_concentration_bfa_153` — Electronic Media (Studio Art)
- `biological_sciences_field_major_ba_or_bachelor_of_general_studies_bgs__191` — Biological Sciences
- `biological_sciences_secondary_education_ba_196` — Biological Sciences: Secondary Education
- `blockchain_certificate_undergraduate` — Blockchain
- `communication_bachelor_of_general_studies_bgs__208` — Communication
- `communication_field_major_207` — Communication
- `communication_sciences_and_disorders_signed_languages_minor` — Signed Languages
- `communication_strategic_communication_emphasis_ba_214` — Communication - Strategic Communication → **renamed to** `communication_communication_studies_emphasis_ba_214`
- `cybersecurity_essentials_certificate_undergraduate` — Cybersecurity Essentials
- `dance_and_digital_performance_certificate_undergraduate` — Dance and Digital Performance
- `data_and_web_security_certificate_undergraduate` — Data and Web Security
- `diversity_in_sports_studies_minor` — Diversity in Sports Studies
- `dyslexia_literacy_certificate_graduate_92` — Dyslexia and Literacy → **renamed to** `reading_specialist_structured_literacy_certificate_graduate_92`
- `education_science_middle__baed_74` — Education - Science (Middle)
- `educational_leadership_educational_psychology_edd` — Educational Leadership - Educational Psychology
- `engineering_education_certificate_graduate_263` — Engineering Education → **renamed to** `teaching_higher_education_certificate_graduate_263`
- `engineering_technology_civil_engineering_technology_concentration_bset_108` — Engineering Technology - Civil Engineering Technology → **renamed to** `applied_engineering_civil_environmental_engineering_bs_108`
- `engineering_technology_engineering_technology_management_concentration_bset_110` — Engineering Technology - Engineering Technology Management → **renamed to** `applied_engineering_engineering_management_bs_110`
- `engineering_technology_facilities_management_concentration_bset` — Engineering Technology - Facilities Management
- `engineering_technology_mechatronics_technology_concentration_bset_111` — Engineering Technology - Mechatronics Technology → **renamed to** `applied_engineering_process_automation_bs_111`
- `ethnic_studies_field_major_or_bachelor_of_general_studies_bgs__225` — Ethnic Studies
- `exercise_science_ba_77` — Exercise Science → **renamed to** `exercise_science_bs_77`
- `exercise_science_med_78` — Exercise Science → **renamed to** `exercise_science_ms_78`
- `fundamentals_of_information_technology_certificate_undergraduate` — Fundamentals of Information Technology
- `global_studies_field_major` — Global Studies
- `health_administration_certificate_graduate_362` — Health Administration → **renamed to** `healthcare_leadership_certificate_graduate_362`
- `health_management_certificate_undergraduate` — Healthcare Leadership
- `higher_education_leadership_certificate_graduate_131` — Higher Education Leadership → **renamed to** `student_affairs_practitioner_wellness_effectiveness_certificate_graduate_131`
- `human_factors_in_security_and_technology_certificate_undergraduate` — Human Factors in Security and Technology
- `insurance_certificate_undergraduate` — Insurance
- `jazz_and_contemporary_media_bm_139` — Jazz and Contemporary Media → **renamed to** `jazz_studies_bm_139`
- `latin_american_latinx_studies_certificate_undergraduate` — Latin American and Latinx Studies
- `leading_and_managing_a_remote_workforce_certificate_undergraduate` — Leading and Managing a Remote Workforce
- `mathematical_foundations_of_data_analysis_ms` — Mathematical Foundations of Data Analysis
- `music_chamber_music_concentration_mm_296` — Music - Chamber Music → **renamed to** `music_performance_chamber_music_concentration_mm_296`
- `music_education_choral_music_concentration_mme_294` — Music Education - Choral Music
- `music_education_elementary_music_concentration_mme_303` — Music Education - Elementary Music
- `music_education_instrumental_conducting_concentration_mme_304` — Music Education - Instrumental Conducting
- `music_education_instrumental_music_concentration_mme_305` — Music Education - Instrumental Music
- `music_education_music_in_special_education_concentration_mme_306` — Music Education - Music in Special Education
- `music_education_voice_concentration_mme_292` — Music Education - Voice
- `music_history_literature_concentration_mm_301` — Music - History and Literature
- `music_opera_performance_concentration_mm_298` — Music - Opera Performance → **renamed to** `music_performance_opera_concentration_mm_298`
- `music_performance_concentration_organ_emphasis_mm_283` — Music - Performance - Organ
- `music_performance_concentration_piano_emphasis_mm_284` — Music - Performance - Piano → **renamed to** `music_performance_piano_concentration_mm_284`
- `music_performance_concentration_strings_winds_and_percussion_emphasis_mm_285` — Music - Performance - Strings, Winds and Percussion → **renamed to** `music_performance_strings_winds_and_percussion_concentration_mm_285`
- `music_performance_concentration_voice_emphasis_mm_293` — Music - Performance - Voice → **renamed to** `music_performance_voice_concentration_mm_293`
- `music_piano_accompanying_concentration_mm_290` — Music - Piano Accompanying
- `nursing_accelerated_program_bsn_177` — Nursing - Accelerated Program
- `nursing_nursing_leadership_and_administration_msn_317` — Nursing - Nursing Leadership and Administration → **renamed to** `nursing_nurse_executive_healthcare_leadership_msn_317`
- `nursing_traditional_program_bsn_179` — Nursing - Traditional Program → **renamed to** `nursing_bsn_179`
- `performing_arts_music_theatre_ba_143` — Music Theatre
- `performing_arts_theatre_performance_bfa_162` — Theatre Performance → **renamed to** `performing_arts_acting_bfa_162`
- `philosophy_analytic_reasoning_ba` — Philosophy - Analytic Reasoning
- `philosophy_ethics_ba` — Philosophy - Ethics
- `philosophy_world_philosophy_ba` — Philosophy - World Philosophy
- `physical_education_coaching_certificate_undergraduate` — Physical Education Coaching
- `physical_education_fitness_certificate_undergraduate` — Physical Education Fitness
- `physical_education_prek_12_ba_79` — Physical Education (PreK-12) → **renamed to** `physical_education_prek_12_baed_79`
- `physical_education_weight_training_certificate_undergraduate` — Physical Education Weight Training
- `physician_assistant_mpa_311` — Physician Associate → **renamed to** `physician_associate_mpa_311`
- `physics_chemical_physics_option_ba_69` — Physics - Chemical Physics Option
- `physics_chemical_physics_option_bs_70` — Physics - Chemical Physics Option
- `physics_engineering_physics_option_ba_71` — Physics - Engineering Physics Option
- `physics_engineering_physics_option_bs_72` — Physics - Engineering Physics Option
- `professional_learning_and_training_certificate_graduate` — Professional Learning and Training
- `public_health_science_certificate_undergraduate` — Public Health Science
- `russian_minor_258` — Russian
- `school_psychology_postbaccalaureate_eds_88` — School Psychology - Postbaccalaureate → **renamed to** `school_psychology_eds_88`
- `school_psychology_postmaster_s_eds_89` — School Psychology - Postmaster's
- `senior_living_management_certificate_graduate` — Senior Living Management
- `special_music_education_bsme_138` — Special Music Education → **renamed to** `special_music_education_bme_138`
- `sustainable_energy_technology_certificate_undergraduate` — Sustainable Energy Technology
- `sustainable_water_technology_certificate_undergraduate` — Sustainable Water Technology
- `theatre_physical_performance_studies_certificate_undergraduate` — Theatre - Physical Performance Studies

## Matched pages whose title changed (20)

- `aging_studies_certificate_graduate`: “Aging Studies for Health Professions” → “Aging Studies for Health Professionals”
- `aging_studies_certificate_undergraduate`: “Aging Studies” → “Aging Studies for Health Professionals”
- `communication_journalism_emphasis_ba_212`: “Communication - Journalism” → “Communication - Journalism and Media Production”
- `dental_hygiene_entry_level_program_bs_169`: “Dental Hygiene, Entry Level Program” → “Dental Hygiene”
- `earth_environmental_and_physical_sciences_ms_324`: “Earth, Environmental and Physical Sciences” → “Earth”
- `economic_development_certificate_graduate_343`: “Economic Development, Graduate Certificate” → “Economic Development”
- `economics_ba_49`: “Economics, Major” → “Economics”
- `economics_minor_47`: “Economics, Minor” → “Economics”
- `education_early_childhood_unified_elementary_education_apprentice_baed_50`: “Early Childhood Unified / Elementary Education - Teacher Apprentice Program” → “Early Childhood Unified / Elementary Education - Teacher Apprentice Program™”
- `education_history_government_and_social_studies_secondary__baed_59`: “Education - History, Government and Social Studies (Secondary)” → “Education - History”
- `english_ba_220`: “English” → “English Language and Literature”
- `graphic_design_communication_minor_215`: “Graphic Design Communication, Minor” → “Graphic Design Communication”
- `kodaly_method_certificate_graduate`: “Kodaly Methodology” → “Kodaly Method”
- `ma_aging_studies_to_health_administration_mha`: “MA in Aging Studies to Health Administration” → “MA in Aging Studies to Master of Health Administration”
- `music_theatre_minor`: “Music Theatre” → “Musical Theatre”
- `performing_arts_music_theatre_bfa_144`: “Music Theatre” → “Musical Theatre”
- `school_of_art_design_and_creative_industries_minor_120`: “Art, Design and Creative Industries” → “Art”
- `special_music_education_adaptive_certificate_graduate`: “Special Music Education / Adaptive Music” → “Special Music Education - Adaptive Music”
- `women_s_studies_ba_276`: “Women's, Ethnicity and Intersectional Studies” → “Women's”
- `womens_ethnicity_intersectional_studies_minor`: “Women's, Ethnicity and Intersectional Studies” → “Women's”

## Graduate pages with no database row (42)

- `advanced_professional_teaching_learning_skills_certificate_graduate` — Details: Advanced Professional Teaching and Learning Skills, Graduate Certificate, CED
- `athletic_training_accelerated_bachelors_to_masters` — Details: Athletic Training, Bachelor's to Master's, CHP
- `computing_systems_certificate_graduate` — Details: Computing Systems, Graduate Certificate, ENG
- `control_systems_certificate_graduate` — Details: Control Systems, Graduate Certificate, ENG
- `criminal_intelligence_certificate_graduate` — Details: Criminal Intelligence, Graduate Certificate, LAS
- `data-centric_modern_communications_certificate_graduate` — Details: Data-Centric Modern Communications, Graduate Certificate, ENG
- `education_behavioral_studies_clinical_mental_health_counselor_phd` — Details: Education and Behavioral Studies - Clinical Mental Health Counselor Education and Supervision, Doctorate, CED
- `education_behavioral_studies_ed_psych_phd` — Details: Education and Behavioral Studies - Educational Psychology, Doctorate, CED
- `energy_engineering_certificate_graduate` — Details: Energy Engineering, Graduate Certificate, ENG
- `exercise_science_ms_78` — Details: Exercise Science, Master's, CHP
- `forensic_biology_ms` — Details: Forensic Biology, Master's, LAS
- `forensic_firearms_ms` — Details: Forensic Firearms, Master's, LAS
- `healthcare_leadership_certificate_graduate_362` — Details: Healthcare Leadership, Graduate Certificate, CHP
- `law_enforcement_local_government_administration_certificate_graduate` — Details: Law Enforcement and Local Government Administration, Graduate Certificate, LAS
- `learning_and_instructional_design_accelerated_bachelors_to_masters` — Details: Learning and Instructional Design, Bachelor's to Master's, CED
- `mathematical_data_science_ms` — Details: Mathematical Data Science, Master's, LAS
- `music_composition_certificate_graduate` — Details: Music Composition, Graduate Certificate, CFA
- `music_education_mme` — Details: Music Education, Master's, CFA
- `music_performance_chamber_music_concentration_mm_296` — Details: Music - Performance - Chamber Music, Master's, CFA
- `music_performance_opera_concentration_mm_298` — Details: Music - Opera Performance, Master's, CFA
- `music_performance_piano_concentration_mm_284` — Details: Music - Performance - Piano , Master's, CFA
- `music_performance_strings_winds_and_percussion_concentration_mm_285` — Details: Music - Performance - Strings, Winds and Percussion , Master's, CFA
- `music_performance_voice_concentration_mm_293` — Details: Music - Performance - Voice , Master's, CFA
- `music_theory_certificate_graduate` — Details: Music Theory, Graduate Certificate, CFA
- `musicology_certificate_graduate` — Details: Musicology, Graduate Certificate, CFA
- `nursing_nurse_executive_healthcare_leadership_msn_317` — Details: Nursing - Nurse Executive and Healthcare Leadership, Master's, CHP
- `physician_associate_mpa_311` — Details: Physician Associate, Master's, CHP
- `power_system_operations_certificate_graduate` — Details: Power System Operations, Graduate Certificate, ENG
- `power_system_planning_certificate_graduate` — Details: Power System Planning, Graduate Certificate, ENG
- `reading_specialist_structured_literacy_certificate_graduate_92` — Details: Reading Specialist and Structured Literacy, Graduate Certificate, CED
- `renewable_energy_storage_certificate_graduate` — Details: Renewable Energy and Storage, Graduate Certificate, ENG
- `school_psychology_eds_88` — Details: School Psychology - Specialist, CED
- `special_education_high_incidence_accelerated_bachelors_to_masters` — Details: Special Education - High Incidence, Bachelor's to Master's, CED
- `special_education_high_incidence_certificate_graduate` — Details: Special Education - High Incidence, Graduate Certificate, CED
- `special_education_low_incidence_accelerated_bachelors_to_masters` — Details: Special Education - Low Incidence, Bachelor's to Master's, CED
- `special_education_low_incidence_alternative_certification_med` — Details: Special Education - Low Incidence Alternative Certification, Master's, CED
- `special_education_low_incidence_certificate_graduate` — Details: Special Education - Low Incidence, Graduate Certificate, CED
- `student_affairs_practitioner_wellness_effectiveness_certificate_graduate_131` — Details: Student Affairs Practitioner Wellness and Effectiveness, Graduate Certificate, CED
- `teaching_excellence_leadership_certificate_graduate` — Details: Teaching Excellence and Leadership, Graduate Certificate, CED
- `teaching_higher_education_certificate_graduate_263` — Details: Teaching in Higher Education, Graduate Certificate, ENG/CED
- `transportation_electrification_certificate_graduate` — Details: Transportation Electrification, Graduate Certificate, ENG
- `urban_policy_innovation_certificate_graduate` — Details: Urban Policy and Innovation, Graduate Certificate, LAS

## Recommended next step

Write `bin/majors-import.php`: read every `www:/academics/majors/*.pcf` through the MC API (cached copies already at hand), parse title/kind/college from the title, the Program Card and teaser sections into the content columns, resolve `{{f:…}}`/`{{d:…}}` links via the API, key rows by numeric id where present and by basename otherwise, mark retired rows inactive rather than deleting them, and record the CMS `file_date` so later runs import only changed pages. Then the Majors editor (phase 2) edits the database, and the CMS pages become the thing generated from it — or stay the source and the import runs on a schedule. That choice decides where marketing edits from now on.
