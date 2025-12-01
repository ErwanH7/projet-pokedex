import csv
import json

def csv_to_json(input_file, output_file):
    with open(input_file, "r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        columns = reader.fieldnames  # garde les noms des colonnes

        data = []
        for row in reader:
            # on cherche une colonne ID (ex: "id", "ID", "Id")
            id_col = next((c for c in columns if c.lower() == "id"), columns[0])

            # récupérer ID (ou vide)
            raw_id = row.get(id_col)
            entry_id = raw_id.strip() if isinstance(raw_id, str) else ""

            data.append({
                "id": entry_id,
                "columns": {col: (row[col] if row[col] is not None else "") for col in columns}
            })

    with open(output_file, "w", encoding="utf-8") as f_out:
        json.dump(data, f_out, indent=4, ensure_ascii=False)

    print("Conversion terminée avec succès !")

# Exemple d'utilisation
input_file = "csv/Ultimate_Living_Dex_v3.0-ZA_mega.csv"
output_file = "csv/Ultimate_Living_Dex_ZA_mega.json"
csv_to_json(input_file, output_file)
