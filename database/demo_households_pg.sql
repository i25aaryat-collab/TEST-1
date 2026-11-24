-- demo_households_pg.sql  —  demo data for Households (Supabase / Postgres)
-- Run AFTER migration_v14_households_pg.sql. Only demo accounts are changed, plus
-- memberships that put Aarya, Hemank and Aditi into a household with their own matches.
-- Every household is made the same way the site makes one: members are people
-- already matched, flats are saved by a member, votes are one per person.
DO $$
BEGIN
    -- 1. How many people each demo person wants to live with (real accounts pick their own)
    UPDATE profiles p SET flatmates = (ARRAY[1,1,2,2,2,3,3,1,2,4])[1 + abs(hashtext(u2.email || 'fm')) % 10]
    FROM users u2 WHERE u2.id = p.user_id AND p.flatmates IS NULL
      AND split_part(u2.email, '@', 2) IN ('demo.roomiematch.in', 'example.com', 'roomiematch.test');
END $$;

CREATE OR REPLACE FUNCTION pg_temp.uid(e TEXT) RETURNS INT LANGUAGE sql AS $f$ SELECT id FROM users WHERE email = e $f$;

-- make sure two demo people are matched (households are only made between matches)
CREATE OR REPLACE FUNCTION pg_temp.match(a TEXT, b TEXT) RETURNS VOID LANGUAGE sql AS $f$
    INSERT INTO matches (user1_id, user2_id)
    SELECT LEAST(pg_temp.uid(a), pg_temp.uid(b)), GREATEST(pg_temp.uid(a), pg_temp.uid(b))
    WHERE NOT EXISTS (SELECT 1 FROM matches WHERE user1_id = LEAST(pg_temp.uid(a), pg_temp.uid(b)) AND user2_id = GREATEST(pg_temp.uid(a), pg_temp.uid(b)));
$f$;

-- a household: name, creator, members, flats to save (by the creator), poll open?
CREATE OR REPLACE FUNCTION pg_temp.household(gname TEXT, creator TEXT, members TEXT[], flats INT[], poll BOOLEAN, settled BOOLEAN, days_ago INT)
RETURNS INT LANGUAGE plpgsql AS $f$
DECLARE g INT; m TEXT; f INT;
BEGIN
    SELECT id INTO g FROM flat_groups WHERE name = gname AND created_by = pg_temp.uid(creator);
    IF g IS NOT NULL THEN RETURN g; END IF;                       -- already there: run is idempotent
    INSERT INTO flat_groups (name, created_by, poll_open, created_at, settled_at)
    VALUES (gname, pg_temp.uid(creator), CASE WHEN poll THEN 1 ELSE 0 END,
            NOW() - make_interval(days => days_ago), CASE WHEN settled THEN NOW() - make_interval(days => GREATEST(days_ago - 3, 0)) END)
    RETURNING id INTO g;
    FOREACH m IN ARRAY array_prepend(creator, members) LOOP
        INSERT INTO group_members (group_id, user_id) VALUES (g, pg_temp.uid(m)) ON CONFLICT DO NOTHING;
    END LOOP;
    FOREACH f IN ARRAY flats LOOP
        INSERT INTO shortlist (group_id, listing_id, added_by) VALUES (g, f, pg_temp.uid(creator)) ON CONFLICT DO NOTHING;
    END LOOP;
    IF settled THEN
        UPDATE profiles SET looking = 0 WHERE user_id IN (SELECT user_id FROM group_members WHERE group_id = g);
    END IF;
    RETURN g;
END $f$;

-- 2. Aarya with two of her matches, hunting in Indore: 3 flats saved, poll open, 2 votes in
UPDATE profiles SET flatmates = 2 WHERE user_id IN (pg_temp.uid('priya.nair4@demo.roomiematch.in'), pg_temp.uid('ananya.singh2@demo.roomiematch.in'));
SELECT pg_temp.household('Indore trio', 'priya.nair4@demo.roomiematch.in',
       ARRAY['i25aaryat@iimidr.ac.in', 'ananya.singh2@demo.roomiematch.in'],
       ARRAY(SELECT id FROM listings WHERE status = 'open' AND city = 'Indore' AND user_id IN (pg_temp.uid('priya.nair4@demo.roomiematch.in'), pg_temp.uid('ananya.singh2@demo.roomiematch.in'))
             UNION SELECT id FROM (SELECT id FROM listings WHERE status = 'open' AND city = 'Indore' AND loc_approx = 0 ORDER BY rent LIMIT 1) x),
       TRUE, FALSE, 5);
INSERT INTO poll_votes (group_id, user_id, listing_id)
SELECT g.id, pg_temp.uid(v.who), (SELECT listing_id FROM shortlist s WHERE s.group_id = g.id ORDER BY listing_id LIMIT 1 OFFSET v.pick)
FROM flat_groups g, (VALUES ('priya.nair4@demo.roomiematch.in', 1), ('ananya.singh2@demo.roomiematch.in', 1)) v(who, pick)
WHERE g.name = 'Indore trio' ON CONFLICT DO NOTHING;

-- 3. Hemank with two of his matches: just started, one flat saved, still looking for a third flat
UPDATE profiles SET flatmates = 2 WHERE user_id IN (pg_temp.uid('arjun.mehta15@demo.roomiematch.in'), pg_temp.uid('aditya.joshi7@demo.roomiematch.in'));
SELECT pg_temp.household('Hostel escape', 'arjun.mehta15@demo.roomiematch.in',
       ARRAY['i25hemankc@iimidr.ac.in', 'aditya.joshi7@demo.roomiematch.in'],
       ARRAY(SELECT id FROM listings WHERE status = 'open' AND city = 'Indore' ORDER BY rent DESC LIMIT 2), FALSE, FALSE, 2);

-- 4. Aditi and Meera: a full household of two, ready to be settled (the "We've found our people" demo)
UPDATE profiles SET flatmates = 1 WHERE user_id = pg_temp.uid('meera.joshi@roomiematch.test');
SELECT pg_temp.household('Two of us', 'meera.joshi@roomiematch.test', ARRAY['i25aditis@iimidr.ac.in'],
       ARRAY(SELECT id FROM listings WHERE status = 'open' AND user_id = pg_temp.uid('meera.joshi@roomiematch.test')), FALSE, FALSE, 4);

-- 5. Two households that have already settled: their members no longer show up in Discover
SELECT pg_temp.match('kavya.shetty102@demo.roomiematch.in', 'prachi.jain103@demo.roomiematch.in');
SELECT pg_temp.match('kavya.shetty102@demo.roomiematch.in', 'pooja.shenoy107@demo.roomiematch.in');
SELECT pg_temp.match('prachi.jain103@demo.roomiematch.in', 'pooja.shenoy107@demo.roomiematch.in');
UPDATE profiles SET flatmates = 2 WHERE user_id IN (pg_temp.uid('kavya.shetty102@demo.roomiematch.in'), pg_temp.uid('prachi.jain103@demo.roomiematch.in'), pg_temp.uid('pooja.shenoy107@demo.roomiematch.in'));
SELECT pg_temp.household('Koramangala girls', 'prachi.jain103@demo.roomiematch.in',
       ARRAY['kavya.shetty102@demo.roomiematch.in', 'pooja.shenoy107@demo.roomiematch.in'],
       ARRAY(SELECT id FROM listings WHERE status = 'open' AND user_id = pg_temp.uid('prachi.jain103@demo.roomiematch.in')), FALSE, TRUE, 12);

SELECT pg_temp.match('devansh.gupta17@demo.roomiematch.in', 'ritik.saxena45@demo.roomiematch.in');
UPDATE profiles SET flatmates = 1 WHERE user_id IN (pg_temp.uid('devansh.gupta17@demo.roomiematch.in'), pg_temp.uid('ritik.saxena45@demo.roomiematch.in'));
SELECT pg_temp.household('Pune flat 4B', 'devansh.gupta17@demo.roomiematch.in', ARRAY['ritik.saxena45@demo.roomiematch.in'],
       ARRAY(SELECT id FROM listings WHERE status = 'open' AND city = 'Pune' ORDER BY id LIMIT 1), FALSE, TRUE, 9);
