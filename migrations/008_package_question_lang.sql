-- Migration 008: Tambah kolom question_lang di tabel packages
-- Menentukan bahasa tampilan pertanyaan pada kuesioner
-- Nilai: 'both' (default), 'id', 'en'

ALTER TABLE packages
ADD COLUMN IF NOT EXISTS question_lang ENUM('both','id','en') NOT NULL DEFAULT 'both'
AFTER is_self_reflection;
