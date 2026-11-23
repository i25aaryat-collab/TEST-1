-- Fills the new compatibility test for FICTIONAL demo profiles only (never the IPM batch accounts).
-- Uses old profile data where it exists; other answers are random, ~15% left skipped.
UPDATE profiles p SET
  answers = (SELECT json_object_agg(k, v)::text FROM (
      SELECT k, CASE k
          WHEN 'bedtime'  THEN LEAST(5, GREATEST(1, p.sleep_slot))
          WHEN 'tidy'     THEN LEAST(5, GREATEST(1, p.cleanliness))
          WHEN 'smoking'  THEN CASE p.ok_smoking  WHEN 'yes' THEN 5 WHEN 'maybe' THEN 3 ELSE 1 END
          WHEN 'drinking' THEN CASE p.ok_drinking WHEN 'yes' THEN 5 WHEN 'maybe' THEN 3 ELSE 1 END
          WHEN 'pets'     THEN CASE p.ok_pets     WHEN 'yes' THEN 5 WHEN 'maybe' THEN 3 ELSE 1 END
          ELSE 1 + floor(random() * 5)::int END AS v
      FROM unnest(ARRAY['bedtime','waketime','light','noise','tidy','dishes','help','day_guests','overnight','notice','shared',
                        'nonveg','groceries','culture','pets','smoking','drinking','ac_temp','ac_use']) AS k
      WHERE k IN ('bedtime','tidy','dishes','day_guests','nonveg','pets') OR random() > 0.15 + 0 * p.user_id) q),
  age_min = GREATEST(17, p.age - 2 - floor(random() * 3)::int),
  age_max = p.age + 3 + floor(random() * 4)::int
FROM users u
WHERE u.id = p.user_id AND u.role = 'user' AND (u.email LIKE '%@example.com' OR (u.email LIKE '%@roomiematch.test' AND u.email NOT LIKE '%@ipm.roomiematch.test')) AND p.answers IS NULL;
