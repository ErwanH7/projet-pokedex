-- Migration : ajout du suivi Alpha/Baron
-- À exécuter UNE SEULE FOIS sur chaque base de données (locale et Hostinger)

ALTER TABLE user_progress
    ADD COLUMN alpha BOOLEAN NOT NULL DEFAULT FALSE;
