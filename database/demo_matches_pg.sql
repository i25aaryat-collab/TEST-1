-- Demo people like back / like the real accounts (picked by compatibility score). Safe to re-run.
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 6, 17, 'like', '2026-11-22 22:14:53' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 6 AND swiped_id = 17);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 6, 17, '2026-11-22 23:24:34' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 6 AND user2_id = 17);
INSERT INTO messages (match_id, sender_id, body, kind, created_at) SELECT m.id, 6, 'Hi! Our sleep times look really similar, that is rare. When are you planning to move?', 'text', '2026-11-23 00:08:49' FROM matches m WHERE m.user1_id = 6 AND m.user2_id = 17 AND NOT EXISTS (SELECT 1 FROM messages x WHERE x.match_id = m.id);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 58, 17, 'like', '2026-11-23 05:10:40' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 58 AND swiped_id = 17);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 17, 58, '2026-11-23 06:36:42' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 17 AND user2_id = 58);
INSERT INTO messages (match_id, sender_id, body, kind, created_at) SELECT m.id, 58, 'Hello! Want to see a couple of flats together this weekend?', 'text', '2026-11-23 07:10:05' FROM matches m WHERE m.user1_id = 17 AND m.user2_id = 58 AND NOT EXISTS (SELECT 1 FROM messages x WHERE x.match_id = m.id);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 54, 17, 'like', '2026-11-23 11:59:41' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 54 AND swiped_id = 17);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 17, 54, '2026-11-23 13:11:35' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 17 AND user2_id = 54);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 5, 17, 'like', '2026-11-23 19:22:24' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 5 AND swiped_id = 17);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 5, 17, '2026-11-23 20:49:08' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 5 AND user2_id = 17);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 56, 17, 'like', '2026-11-24 01:57:39' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 56 AND swiped_id = 17);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 17, 56, '2026-11-24 02:54:44' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 17 AND user2_id = 56);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 67, 52, 'like', '2026-11-22 22:20:18' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 67 AND swiped_id = 52);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 52, 67, '2026-11-22 23:06:01' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 52 AND user2_id = 67);
INSERT INTO messages (match_id, sender_id, body, kind, created_at) SELECT m.id, 67, 'Hey! Saw we matched, are you still looking around Vijay Nagar?', 'text', '2026-11-22 23:50:41' FROM matches m WHERE m.user1_id = 52 AND m.user2_id = 67 AND NOT EXISTS (SELECT 1 FROM messages x WHERE x.match_id = m.id);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 59, 52, 'like', '2026-11-23 04:58:13' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 59 AND swiped_id = 52);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 52, 59, '2026-11-23 05:39:09' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 52 AND user2_id = 59);
INSERT INTO messages (match_id, sender_id, body, kind, created_at) SELECT m.id, 59, 'Hi! Our sleep times look really similar, that is rare. When are you planning to move?', 'text', '2026-11-23 05:48:07' FROM matches m WHERE m.user1_id = 52 AND m.user2_id = 59 AND NOT EXISTS (SELECT 1 FROM messages x WHERE x.match_id = m.id);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 58, 52, 'like', '2026-11-23 12:30:12' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 58 AND swiped_id = 52);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 52, 58, '2026-11-23 13:13:08' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 52 AND user2_id = 58);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 8, 52, 'like', '2026-11-23 19:12:02' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 8 AND swiped_id = 52);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 8, 52, '2026-11-23 20:12:23' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 8 AND user2_id = 52);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 54, 52, 'like', '2026-11-24 01:41:12' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 54 AND swiped_id = 52);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 52, 54, '2026-11-24 02:34:55' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 52 AND user2_id = 54);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 54, 19, 'like', '2026-11-22 22:20:24' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 54 AND swiped_id = 19);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 19, 54, '2026-11-22 23:19:56' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 19 AND user2_id = 54);
INSERT INTO messages (match_id, sender_id, body, kind, created_at) SELECT m.id, 54, 'Hi :) Do you cook at home much? Trying to figure out the kitchen situation.', 'text', '2026-11-22 23:33:49' FROM matches m WHERE m.user1_id = 19 AND m.user2_id = 54 AND NOT EXISTS (SELECT 1 FROM messages x WHERE x.match_id = m.id);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 2, 19, 'like', '2026-11-23 04:54:38' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 2 AND swiped_id = 19);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 2, 19, '2026-11-23 05:45:02' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 2 AND user2_id = 19);
INSERT INTO messages (match_id, sender_id, body, kind, created_at) SELECT m.id, 2, 'Hey! Saw we matched, are you still looking around Vijay Nagar?', 'text', '2026-11-23 05:55:07' FROM matches m WHERE m.user1_id = 2 AND m.user2_id = 19 AND NOT EXISTS (SELECT 1 FROM messages x WHERE x.match_id = m.id);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 5, 19, 'like', '2026-11-23 12:01:58' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 5 AND swiped_id = 19);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 5, 19, '2026-11-23 12:16:40' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 5 AND user2_id = 19);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 9, 19, 'like', '2026-11-23 19:18:15' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 9 AND swiped_id = 19);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 9, 19, '2026-11-23 19:39:51' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 9 AND user2_id = 19);
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at) SELECT 3, 19, 'like', '2026-11-24 01:54:16' WHERE NOT EXISTS (SELECT 1 FROM swipes WHERE swiper_id = 3 AND swiped_id = 19);
INSERT INTO matches (user1_id, user2_id, created_at) SELECT 3, 19, '2026-11-24 02:40:32' WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = 3 AND user2_id = 19);
-- Two "liked you" requests per real account: demo people this account hasn't swiped on (and who haven't swiped on it),
-- nearest cities first, then closest in age. They wait on the Matches page for a yes/no.
INSERT INTO swipes (swiper_id, swiped_id, direction, created_at)
SELECT pick.cid, pick.uid, 'like', NOW() - (pick.rn * INTERVAL '3 hours')
FROM (
    SELECT me.user_id AS uid, c.user_id AS cid,
           ROW_NUMBER() OVER (PARTITION BY me.user_id ORDER BY CASE c.city WHEN me.city THEN 0 WHEN 'Bhopal' THEN 1 ELSE 2 END, ABS(c.age - me.age), c.user_id) AS rn
    FROM profiles me
    JOIN users mu ON mu.id = me.user_id AND mu.email IN ('i25aaryat@iimidr.ac.in', 'i25hemankc@iimidr.ac.in', 'i25aditis@iimidr.ac.in')
    JOIN profiles c ON c.user_id <> me.user_id
    JOIN users cu ON cu.id = c.user_id AND cu.role = 'user' AND (cu.email LIKE '%@demo.roomiematch.in' OR cu.email LIKE '%@example.com')
    WHERE NOT EXISTS (SELECT 1 FROM swipes s WHERE (s.swiper_id = me.user_id AND s.swiped_id = c.user_id) OR (s.swiper_id = c.user_id AND s.swiped_id = me.user_id))
      AND NOT EXISTS (SELECT 1 FROM swipes s WHERE s.swiper_id = c.user_id AND s.swiped_id = me.user_id)
) pick
WHERE pick.rn <= 2
  AND (SELECT COUNT(*) FROM swipes r WHERE r.swiped_id = pick.uid AND r.direction = 'like'
       AND NOT EXISTS (SELECT 1 FROM swipes b WHERE b.swiper_id = pick.uid AND b.swiped_id = r.swiper_id)) < 2;
