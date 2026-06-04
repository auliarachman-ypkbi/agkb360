-- ═══════════════════════════════════════════════════════════
-- SEED PRODUCTION: Superadmin, Admin, Tester only
-- ═══════════════════════════════════════════════════════════

-- Superadmin
INSERT INTO users (name, email, password, role, is_active) VALUES
('Super Administrator', 'superadmin@akgb360.app', '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'superadmin', 1);

-- Admin
INSERT INTO users (name, email, password, role, is_active) VALUES
('Admin Satu',  'admin1@akgb360.app', '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'admin', 1),
('Admin Dua',   'admin2@akgb360.app', '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'admin', 1),
('Admin Tiga',  'admin3@akgb360.app', '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'admin', 1),
('Admin Empat', 'admin4@akgb360.app', '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'admin', 1),
('Admin Lima',  'admin5@akgb360.app', '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'admin', 1);

-- Tester
INSERT INTO users (name, email, password, role, is_active) VALUES
('Tester Satu',    'tester1@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Dua',     'tester2@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Tiga',    'tester3@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Empat',   'tester4@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Lima',    'tester5@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Enam',    'tester6@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Tujuh',   'tester7@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Delapan', 'tester8@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Sembilan','tester9@akgb360.app',  '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1),
('Tester Sepuluh', 'tester10@akgb360.app', '$2y$10$UnoqvaiFtYb.j7Z1iWiRB.AXSSZGraoRGxnlRQWrCqX2MEsK/7Tgy', 'tester', 1);
