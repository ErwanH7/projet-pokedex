import os
import pandas as pd

# Afficher le dossier courant
print("Dossier courant :", os.getcwd())

# Lister tous les fichiers CSV (insensible à la casse)
fichiers = [f for f in os.listdir('.') if f.lower().endswith('.csv')]

if not fichiers:
    print("Aucun fichier CSV trouvé dans le dossier !")
else:
    for fichier in fichiers:
        try:
            # Lire le CSV (détecte automatiquement le séparateur)
            df = pd.read_csv(fichier, sep=None, engine='python')
            print(f"\nColonnes de '{fichier}':")
            print(list(df.columns))
        except Exception as e:
            print(f"Impossible de lire '{fichier}': {e}")
