CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    username VARCHAR(50),
    preferred_language ENUM('fr', 'en', 'de') DEFAULT 'fr',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 1. rendre email NOT NULL (si ce n'est pas le cas) et unique (si besoin)
ALTER TABLE users
  MODIFY COLUMN email VARCHAR(255) NOT NULL,
  ADD UNIQUE KEY ux_users_email (email);

-- 2. ajouter la colonne password_hash si elle n'existe pas
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL;

-- 3. ajouter la colonne role pour gérer admin/user (default 'user')
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS role ENUM('user','admin') NOT NULL DEFAULT 'user';


CREATE TABLE pokedex_list (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL,   -- ex: "ZA", "SV", "ULT"
    name VARCHAR(100) NOT NULL          -- ex: "Pokédex ZA"
);

CREATE TABLE pokemon (
    id INT PRIMARY KEY,
    sprite VARCHAR(20),
    shiny_sprite VARCHAR(20),

    -- Langues
    name_fr VARCHAR(255),
    name_en VARCHAR(255),
    name_de VARCHAR(255)
);

CREATE TABLE pokemon_forms (
    id VARCHAR(100) PRIMARY KEY,       -- ex: 3, 3_m, 3_f, 3_mega
    pokemon_id INT NOT NULL,           -- référence à pokemon.id
    form_code VARCHAR(20),             -- ex : base, m, f, mega, gmax
    sprite VARCHAR(255),
    shiny_sprite VARCHAR(255),
    FOREIGN KEY (pokemon_id) REFERENCES pokemon(id)
);


CREATE TABLE pokedex_entries (
    pokedex_id INT NOT NULL,
    pokemon_id VARCHAR(100) NOT NULL,
    PRIMARY KEY (pokedex_id, pokemon_id),
    FOREIGN KEY (pokedex_id) REFERENCES pokedex_list(id),
    FOREIGN KEY (pokemon_id) REFERENCES pokemon_forms(id)
);


CREATE TABLE user_progress (
    user_id INT NOT NULL,
    pokedex_id INT NOT NULL,
    pokemon_id VARCHAR(100) NOT NULL,
    caught BOOLEAN DEFAULT FALSE,

    PRIMARY KEY (user_id, pokedex_id, pokemon_id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (pokedex_id) REFERENCES pokedex_list(id),
    FOREIGN KEY (pokemon_id) REFERENCES pokemon_forms(id)
);