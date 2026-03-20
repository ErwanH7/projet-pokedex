-- Migration : ajout des colonnes name_en, name_de et name_es à pokedex_list
ALTER TABLE pokedex_list
  ADD COLUMN IF NOT EXISTS name_en VARCHAR(100) NULL AFTER name,
  ADD COLUMN IF NOT EXISTS name_de VARCHAR(100) NULL AFTER name_en,
  ADD COLUMN IF NOT EXISTS name_es VARCHAR(100) NULL AFTER name_de;

-- Migration : ajout de la colonne name_es à pokemon
ALTER TABLE pokemon
  ADD COLUMN IF NOT EXISTS name_es VARCHAR(100) NULL AFTER name_de;
