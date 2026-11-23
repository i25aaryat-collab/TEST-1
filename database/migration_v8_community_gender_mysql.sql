ALTER TABLE communities ADD COLUMN topic VARCHAR(30) NULL, ADD COLUMN about VARCHAR(200) NULL;
ALTER TABLE profiles ADD COLUMN gender_text VARCHAR(40) NULL, MODIFY gender ENUM('male','female','nosay','other') NOT NULL;
