import csv
import json
import re
import urllib.request
import ssl
import os

# Noms de colonnes reconnus comme numéro national
NATIONAL_COL_NAMES = ["national dex", "nat. dex", "national", "nat dex", "ndex", "national #", "nat #", "#", "num"]

def find_national_col(columns):
    for col in columns:
        if col.lower().strip() in NATIONAL_COL_NAMES:
            return col
    return None

def detect_form_code(name):
    """Détecte le code de forme à partir du nom"""
    if not name:
        return None
    name_lower = name.lower()

    if '♂' in name: return 'm'
    if '♀' in name: return 'f'
    if 'mega' in name_lower: return 'mega'

    for regional in ['alolan', 'galarian', 'hisuian', 'paldean']:
        if regional in name_lower:
            return regional

    if 'zygarde' in name_lower:
        if '10%' in name: return '10'
        if '50%' in name: return '50'

    if 'az' in name_lower and 'floette' in name_lower:
        return 'azett'

    for pattern in ['meadow', 'marine', 'garden', 'elegant', 'high plains', 'modern',
                    'monsoon', 'ocean', 'sandstorm', 'savanna', 'sun', 'tundra', 'poké ball', 'fancy']:
        if pattern + ' pattern' in name_lower:
            return pattern.replace(' ', '_')

    for trim in ['natural', 'heart trim', 'star trim', 'diamond trim', 'la reine trim',
                 'kabuki trim', 'pharaoh trim', 'debutante trim', 'matron trim', 'dandy trim']:
        if trim in name_lower:
            return trim.replace(' ', '_').replace(' trim', '')

    for size in ['small', 'large', 'super']:
        if size in name_lower:
            return size

    return 'base'

def name_to_pokeapi_slug(name):
    """Convertit un nom Pokémon en slug PokéAPI (ex: 'Mr. Mime' → 'mr-mime')"""
    # Supprimer les symboles de genre et formes entre parenthèses
    name = re.sub(r'[♂♀]', '', name)
    name = re.sub(r'\(.*?\)', '', name)
    # Mots à ignorer pour les formes (le slug correspond à l'espèce de base)
    ignore_words = ['Alolan', 'Galarian', 'Hisuian', 'Paldean', 'Mega', 'Gigantamax',
                    'Male', 'Female', '10%', '50%', 'AZ']
    for word in ignore_words:
        name = re.sub(rf'\b{re.escape(word)}\b', '', name, flags=re.IGNORECASE)
    name = name.strip()
    # Convertir en slug : minuscules, remplacer espaces/points/apostrophes par tirets
    slug = name.lower()
    slug = re.sub(r"[.\s']+", '-', slug)
    slug = re.sub(r'-+', '-', slug).strip('-')
    return slug

# Cache pour éviter les doublons d'appels API
_national_id_cache = {}

def fetch_national_id(name):
    """Interroge PokéAPI pour obtenir l'ID national depuis le nom."""
    slug = name_to_pokeapi_slug(name)
    if not slug:
        return None
    if slug in _national_id_cache:
        return _national_id_cache[slug]

    url = f"https://pokeapi.co/api/v2/pokemon-species/{slug}/"
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'csv_to_json/1.0'})
        with urllib.request.urlopen(req, timeout=10, context=ctx) as resp:
            data = json.loads(resp.read())
            national_id = data['id']
            _national_id_cache[slug] = national_id
            return national_id
    except Exception as e:
        print(f"  ⚠ PokéAPI introuvable pour « {name} » (slug: {slug}) : {e}")
        _national_id_cache[slug] = None
        return None

def csv_to_json(input_file, output_file, regional_col=None, national_col=None, use_pokeapi=False):
    """
    Convertit un CSV Pokédex en JSON structuré.

    Paramètres :
      regional_col  : nom de la colonne contenant le numéro régional (auto-détecté si None)
      national_col  : nom de la colonne contenant le numéro national (auto-détecté si None)
      use_pokeapi   : si True et qu'il n'y a pas de colonne nationale, interroge PokéAPI
                      pour résoudre les IDs nationaux depuis le nom du Pokémon.
    """
    with open(input_file, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        columns = reader.fieldnames

        if regional_col is None:
            regional_col = next((c for c in columns if c.lower() in ["id", "lumiose dex"]), columns[0])

        if national_col is None:
            national_col = find_national_col(columns)

        if national_col:
            print(f"✔ Colonne nationale : '{national_col}'")
            print(f"  Colonne régionale : '{regional_col}'")
            use_pokeapi = False  # Inutile si on a déjà la colonne
        elif use_pokeapi:
            print(f"⚠ Pas de colonne nationale — résolution via PokéAPI (peut être lent).")
            print(f"  Colonne régionale : '{regional_col}'")
        else:
            print(f"⚠ Pas de colonne nationale trouvée.")
            print(f"  L'id sera le numéro RÉGIONAL. Pour les IDs nationaux, passez use_pokeapi=True")
            print(f"  ou ajoutez une colonne 'National Dex' au CSV.")

        data = []
        last_national_id = None
        last_regional_num = None

        for row in reader:
            raw_regional = row.get(regional_col, '').strip()
            raw_national = row.get(national_col, '').strip() if national_col else ''
            name = row.get('Name', '').strip()
            form_code = detect_form_code(name)

            # ---------- Résolution de l'ID national ----------
            if raw_national:
                nat_id = raw_national
                last_national_id = nat_id
                last_regional_num = raw_regional or None

            elif raw_regional:
                last_regional_num = raw_regional

                if use_pokeapi and name:
                    fetched = fetch_national_id(name)
                    if fetched:
                        nat_id = str(fetched)
                        last_national_id = nat_id
                        print(f"  ✔ {name} → national #{nat_id} (régional #{raw_regional})")
                    else:
                        nat_id = raw_regional  # fallback
                        last_national_id = nat_id
                else:
                    nat_id = raw_regional
                    last_national_id = nat_id

            else:
                # Forme sans ID → utilise le dernier national connu
                if not last_national_id:
                    continue
                nat_id = last_national_id

            # ---------- Construction de l'entry_id (national_id + forme) ----------
            if form_code and form_code != 'base':
                entry_id = f"{nat_id}_{form_code}"
            else:
                entry_id = nat_id

            entry = {
                "id": entry_id,
                "columns": {col: (row[col] if row[col] is not None else "") for col in columns}
            }

            # Numéro régional séparé si disponible et différent de l'id
            if last_regional_num and last_regional_num != entry_id:
                entry["regional_number"] = last_regional_num

            # ID national explicite si l'id de l'entrée contient aussi le code de forme
            if use_pokeapi and nat_id != entry_id:
                entry["national_id"] = int(nat_id) if nat_id.isdigit() else nat_id

            data.append(entry)

    with open(output_file, "w", encoding="utf-8") as f_out:
        json.dump(data, f_out, indent=4, ensure_ascii=False)

    print(f"\n✔ Conversion terminée : {len(data)} entrées → {output_file}")


# ---------- Utilisation ----------
script_dir = os.path.dirname(os.path.abspath(__file__))

# Pour le pokédex ZA (pas de colonne nationale dans le CSV) :
# Passe use_pokeapi=True pour résoudre automatiquement les IDs nationaux via PokéAPI.
# C'est plus lent (~1s/Pokémon) mais génère un JSON cohérent avec la DB.
csv_to_json(
    input_file=os.path.join(script_dir, "Ultimate_Living_Dex_ZA.csv"),
    output_file=os.path.join(script_dir, "Ultimate_Living_Dex_ZA.json"),
    use_pokeapi=True   # ← met False si pas de connexion internet
)