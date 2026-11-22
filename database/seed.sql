-- ==========================================================================
-- database/seed.sql  —  Owner: M1 (Lead)
-- --------------------------------------------------------------------------
-- Fake data for testing and the live demo. Import AFTER schema.sql.
--
-- EVERY seed account uses the same password:   Roomie@123
--
--   Admin          admin@roomiematch.test   (admin panel)
--   Demo user A    usera@roomiematch.test   Rohan Verma, Indore
--   Demo user B    userb@roomiematch.test   Arjun Nair, Indore
--
-- A and B score 94% with each other and have NOT swiped on each other,
-- so the team can show a live match with two browser windows.
-- Ananya and Rahul have already liked A, so if A likes either of them the
-- "It's a match!" pop-up appears straight away (a quick backup for the demo).
-- 12 users live in Indore and 3 in Bhopal, to show the city filter.
-- Seed users use the built-in character avatars (css/avatars/), not photos.
-- ==========================================================================

SET NAMES utf8mb4;
SET time_zone = '+05:30';   -- the demo times below are Indian Standard Time

-- Password hash below = password_hash('Roomie@123', PASSWORD_DEFAULT)
INSERT INTO users (id, name, email, password_hash, role, created_at) VALUES
(1,  'RoomieMatch Admin', 'admin@roomiematch.test',           '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'admin', '2026-10-01 09:00:00'),
(2,  'Rohan Verma',       'usera@roomiematch.test',           '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-11-02 10:15:00'),
(3,  'Arjun Nair',        'userb@roomiematch.test',           '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-11-03 18:40:00'),
(4,  'Priya Sharma',      'priya.sharma@roomiematch.test',    '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-12 11:05:00'),
(5,  'Sneha Kulkarni',    'sneha.kulkarni@roomiematch.test',  '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-14 20:30:00'),
(6,  'Vikram Rathore',    'vikram.rathore@roomiematch.test',  '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-18 08:45:00'),
(7,  'Ananya Iyer',       'ananya.iyer@roomiematch.test',     '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-20 16:10:00'),
(8,  'Karan Malhotra',    'karan.malhotra@roomiematch.test',  '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-22 23:55:00'),
(9,  'Meera Joshi',       'meera.joshi@roomiematch.test',     '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-25 13:20:00'),
(10, 'Aditya Patil',      'aditya.patil@roomiematch.test',    '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-27 09:35:00'),
(11, 'Fatima Khan',       'fatima.khan@roomiematch.test',     '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-10-29 17:50:00'),
(12, 'Siddharth Bose',    'siddharth.bose@roomiematch.test',  '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-11-01 02:15:00'),
(13, 'Rahul Reddy',       'rahul.reddy@roomiematch.test',     '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-11-04 12:00:00'),
(14, 'Divya Nambiar',     'divya.nambiar@roomiematch.test',   '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-11-06 07:25:00'),
(15, 'Harsh Agrawal',     'harsh.agrawal@roomiematch.test',   '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-11-08 19:05:00'),
(16, 'Neha Saxena',       'neha.saxena@roomiematch.test',     '$2y$10$hkY/zKJ97HyRMJss44hXn.X2cW/fsyhMfx..ojdas3LS2FgxRQI16', 'user',  '2026-11-10 15:40:00');

-- The admin (id 1) has no profile: admins never appear on the swipe page.
-- ok_*: what they accept in a flatmate (yes / maybe = prefer not / no).
-- min_cleanliness: messiest flatmate accepted. sleep_flex: bedtime gap accepted (slots; 4 = any).
-- sleep_slot: 1 = 8-10 PM, 2 = 10-12, 3 = 12-2 AM, 4 = 2-4 AM, 5 = 4-6 AM
INSERT INTO profiles (user_id, age, gender, city, budget_min, budget_max, sleep_slot, cleanliness, smoking, drinking, pets, languages, bio, photo, ok_smoking, ok_drinking, ok_pets, min_cleanliness, sleep_flex) VALUES
(2,  21, 'male',   'Indore', 8000,  12000, 2, 4, 'no',  'no',  'no',  'Hindi,English,Marathi',          'IPM student who codes late but keeps the kitchen spotless. Looking for a calm flat near Vijay Nagar.', 'avatar:c01', 'no', 'maybe', 'yes', 3, 1),
(3,  22, 'male',   'Indore', 7000,  12000, 2, 5, 'no',  'no',  'no',  'English,Hindi,Marathi,Malayalam', 'Analyst at a fintech startup. Early gym, quiet evenings, and I always wash my dishes the same night.', 'avatar:c02', 'no', 'maybe', 'yes', 3, 1),
(4,  23, 'female', 'Indore', 9000,  14000, 1, 5, 'no',  'no',  'no',  'Hindi,English',                  'Design intern at an ad agency. Plant mom, early riser, chai at 6 AM sharp.', 'avatar:c03', 'maybe', 'yes', 'no', 4, 1),
(5,  24, 'female', 'Indore', 6000,  10000, 3, 3, 'no',  'yes', 'yes', 'Marathi,Hindi,English',          'Content writer working from home. I have a friendly indie cat called Poha.', 'avatar:c05', 'yes', 'yes', 'yes', 2, 2),
(6,  26, 'male',   'Indore', 12000, 18000, 3, 2, 'yes', 'yes', 'no',  'Hindi,Punjabi,English',          'Sales manager, travel a lot for work. Easy-going, weekend cricket fan.', 'avatar:c06', 'yes', 'yes', 'maybe', 1, 4),
(7,  22, 'female', 'Indore', 7000,  11000, 2, 4, 'no',  'no',  'no',  'Tamil,English,Hindi',            'Medical student. Long study hours, so I need a quiet place. I cook South Indian food on Sundays.', 'avatar:c09', 'no', 'yes', 'yes', 3, 1),
(8,  25, 'male',   'Indore', 10000, 15000, 4, 2, 'yes', 'yes', 'yes', 'Hindi,Punjabi,English',          'Night-shift support engineer with a very cuddly beagle. Gaming on weekends.', 'avatar:c08', 'yes', 'yes', 'yes', 1, 4),
(9,  27, 'female', 'Indore', 8000,  13000, 2, 3, 'no',  'yes', 'no',  'Gujarati,Hindi,English',         'Architect who sketches on the balcony. Social, but I respect quiet hours.', 'avatar:c11', 'maybe', 'yes', 'maybe', 2, 2),
(10, 20, 'male',   'Indore', 5000,  8000,  3, 3, 'no',  'no',  'no',  'Marathi,Hindi',                  'Engineering student on a tight budget. Good cook, and I play guitar softly.', 'avatar:c04', 'no', 'maybe', 'yes', 2, 2),
(11, 24, 'female', 'Indore', 7000,  12000, 2, 4, 'no',  'no',  'yes', 'Urdu,Hindi,English',             'Pharmacist. Calm and organised. My parrot only knows one word: chai.', 'avatar:c07', 'no', 'yes', 'yes', 3, 1),
(12, 29, 'male',   'Indore', 15000, 22000, 5, 1, 'yes', 'yes', 'no',  'Bengali,English,Hindi',          'Musician and sound engineer. Late nights in the studio, very chill flatmate.', 'avatar:c12', 'yes', 'yes', 'yes', 1, 4),
(13, 23, 'male',   'Indore', 9000,  12000, 2, 4, 'no',  'yes', 'no',  'Telugu,English,Hindi',           'Data analyst. Weekday routine, weekend trekker. I split chores fairly.', 'avatar:c10', 'maybe', 'yes', 'yes', 3, 2),
(14, 21, 'female', 'Bhopal', 6000,  9000,  1, 4, 'no',  'no',  'no',  'Malayalam,English,Hindi',        'Law student. Early to bed, early to rise, and usually in the library.', 'avatar:c03', 'no', 'no', 'yes', 3, 1),
(15, 26, 'male',   'Bhopal', 10000, 16000, 3, 3, 'no',  'yes', 'no',  'Hindi,English,Gujarati',         'Chartered accountant at a Big Four firm. Busy in audit season, fun the rest of the year.', 'avatar:c02', 'maybe', 'yes', 'yes', 2, 2),
(16, 25, 'female', 'Bhopal', 8000,  12000, 2, 5, 'no',  'no',  'yes', 'Hindi,English,Kannada',          'UX researcher with a golden retriever. Very tidy, and I host board-game nights.', 'avatar:c05', 'no', 'yes', 'yes', 4, 1);

-- Swipes. A (id 2) and B (id 3) have NOT swiped on each other.
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) VALUES
(7,  2, 'like', '2026-11-15 10:00:00'),   -- Ananya liked A  (instant match if A likes back)
(13, 2, 'like', '2026-11-15 11:00:00'),   -- Rahul liked A   (instant match if A likes back)
(2,  9, 'like', '2026-11-12 20:00:00'),   -- A and Meera liked each other -> match 1
(9,  2, 'like', '2026-11-12 21:00:00'),
(4,  3, 'like', '2026-11-16 09:00:00'),   -- Priya liked B
(4,  7, 'like', '2026-11-10 08:00:00'),   -- Priya and Ananya -> match 2
(7,  4, 'like', '2026-11-10 09:30:00'),
(8,  12, 'like', '2026-11-09 23:00:00'),  -- Karan and Siddharth -> match 3
(12, 8, 'like', '2026-11-10 01:00:00'),
(6,  10, 'pass', '2026-11-11 12:00:00'),
(10, 5, 'like', '2026-11-11 13:00:00'),
(5,  10, 'pass', '2026-11-11 14:00:00'),
(15, 16, 'like', '2026-11-13 18:00:00');

-- Matches always store the smaller id in user1_id.
INSERT INTO matches (id, user1_id, user2_id, created_at) VALUES
(1, 2, 9,  '2026-11-12 21:00:00'),
(2, 4, 7,  '2026-11-10 09:30:00'),
(3, 8, 12, '2026-11-10 01:00:00');

INSERT INTO messages (match_id, sender_id, body, created_at) VALUES
(1, 9, 'Hi Rohan! Saw you are also looking around Vijay Nagar.', '2026-11-12 21:05:00'),
(1, 2, 'Hey Meera! Yes, ideally a 2BHK under 12k each. Are you okay with quiet hours after 11?', '2026-11-12 21:12:00'),
(1, 9, 'Totally, I am usually asleep by then anyway. Want to see a flat on Saturday?', '2026-11-12 21:20:00'),
(2, 4, 'Ananya, your Sunday cooking sounds amazing. Fancy splitting a flat near MGM?', '2026-11-10 10:00:00'),
(2, 7, 'Haha yes! As long as you water the plants and I cook, we are set.', '2026-11-10 10:30:00'),
(3, 8, 'Another night owl! What time do you usually get back from the studio?', '2026-11-10 01:10:00'),
(3, 12, 'Around 3 AM most days. Your beagle and I will get along fine.', '2026-11-10 01:25:00');

-- Messages already read (so only new ones count as unread)
UPDATE messages SET read_at = created_at;

-- Rooms on offer
INSERT INTO listings (user_id, title, city, area, rent, room_type, furnished, available_from, description, status, created_at) VALUES
(9,  'Sunny room in a 2BHK near Vijay Nagar', 'Indore', 'Vijay Nagar', 9500,  'private', 'yes', '2026-12-01', 'Big window, attached balcony, 5 minutes to the bus stop. Looking for a calm flatmate who keeps the kitchen clean.', 'open', '2026-11-14 10:00:00'),
(6,  'Shared room, walk to Palasia market',   'Indore', 'Palasia',     6000,  'shared',  'yes', '2026-12-10', 'Shared with one other working professional. Wi-Fi, fridge and washing machine included. Smoking only on the balcony.', 'open', '2026-11-15 18:30:00'),
(12, 'Quiet room above a studio',             'Indore', 'Bhawarkua',   11000, 'private', 'no',  '2027-01-01', 'Unfurnished private room. I work late nights, so it suits a night owl. Pets welcome.', 'open', '2026-11-16 21:00:00'),
(15, 'Room in a 3BHK near DB Mall',           'Bhopal', 'Arera Colony', 8000, 'private', 'yes', '2026-12-05', 'Two flatmates already, both CAs. Cook and maid shared. Non-smokers preferred.', 'open', '2026-11-17 09:15:00');

-- One open report for the admin moderation demo (Karan reported a listing)
INSERT INTO reports (reporter_id, target_type, target_id, reason, created_at) VALUES
(8, 'listing', 2, 'Rent seems wrong and the photos are missing.', '2026-11-18 12:00:00');

-- PostgreSQL only (online copy): skip the "SET NAMES" line at the top, and
-- after the inserts move the id counters past the ids used above:
-- SELECT setval('users_id_seq', 16); SELECT setval('profiles_id_seq', 15);
-- SELECT setval('swipes_id_seq', 13); SELECT setval('matches_id_seq', 3);
-- SELECT setval('messages_id_seq', 7); SELECT setval('listings_id_seq', 4); SELECT setval('reports_id_seq', 1);
