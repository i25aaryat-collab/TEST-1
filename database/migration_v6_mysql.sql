-- v6: chat reactions and typing indicator (MySQL / XAMPP)
CREATE TABLE IF NOT EXISTS message_reactions (
  message_id INT NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  emoji VARCHAR(16) NOT NULL,
  PRIMARY KEY (message_id, user_id));
CREATE TABLE IF NOT EXISTS chat_typing (
  match_id INT NOT NULL REFERENCES matches(id) ON DELETE CASCADE,
  user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (match_id, user_id));
