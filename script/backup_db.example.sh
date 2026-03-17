#!/bin/bash

# ================================================================
# SCRIPT : backup_db.example.sh
# OBJET  : Exemple prêt à copier pour sauvegarder une base en local et,
#          si souhaité, l'envoyer sur un serveur distant.
#
# UTILISATION TYPE :
# - cp backup_db.example.sh backup_db.sh
# - Modifier les variables de configuration
# - Lancer le script puis planifier (cron / tâche planifiée)
#
# SORTIES :
# - Archive SQL compressée (.sql.gz)
# - Logs horodatés dans le dossier logs/
# ================================================================

# ===== COMMANDE POUR L'UTILISER =====
# 1. Copier ce fichier : cp backup_db.example.sh backup_db.sh
# 2. Rendre le script exécutable : chmod +x backup_db.sh
# 3. Adapter la configuration ci-dessous
# 4. Exécuter : ./backup_db.sh

# Nom du script pour le fichier de log.
SCRIPT_NAME="$(basename "$0" .sh)"
# Dossier absolu du script.
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
# Dossier de logs local.
LOG_DIR="$SCRIPT_DIR/logs"
# Créer le dossier logs s'il est absent.
mkdir -p "$LOG_DIR"
# Fichier log journalier.
LOG_FILE="$LOG_DIR/${SCRIPT_NAME}_$(date +"%Y-%m-%d").log"

# Fonction utilitaire de log INFO.
log_info() {
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] [INFO] $1" | tee -a "$LOG_FILE"
}

# Fonction utilitaire de log ERROR.
log_error() {
    echo "[$(date +"%Y-%m-%d %H:%M:%S")] [ERROR] $1" | tee -a "$LOG_FILE" >&2
}

# ===== CONFIGURATION =====
# Utilisateur DB pour mysqldump.
DB_USER="${DB_USER:-root}"
# Mot de passe DB (optionnel).
DB_PASSWORD="${DB_PASSWORD:-}"
# Chemin explicite de mysqldump (optionnel). Exemple: /c/wamp64/bin/mariadb/mariadb11.5.2/bin/mysqldump.exe
MYSQLDUMP="${MYSQLDUMP:-mysqldump}"
# Nom de la base à sauvegarder.
DB_NAME="${DB_NAME:-paaxio_db}"
# Dossier local de sauvegarde.
BACKUP_DIR="${BACKUP_DIR:-dir/backup}"
# Horodatage pour nom de fichier.
DATE=$(date +"%Y-%m-%d_%H-%M")

# ===== SERVEUR EXTERNE (OPTIONNEL) =====
# Active/désactive l'envoi distant.
ENABLE_REMOTE_SYNC="${ENABLE_REMOTE_SYNC:-false}"
REMOTE_USER="${REMOTE_USER:-backup_user}"
REMOTE_HOST="${REMOTE_HOST:-backup.example.com}"
REMOTE_PORT="${REMOTE_PORT:-22}"
REMOTE_DIR="${REMOTE_DIR:-/srv/backups/paaxio}"
SSH_KEY="${SSH_KEY:-}"

# Construire option mot de passe non interactive.
MYSQL_PWD_OPT=""
if [ -n "$DB_PASSWORD" ]; then
    MYSQL_PWD_OPT="-p$DB_PASSWORD"
fi

# Si MYSQLDUMP n'est pas fourni et introuvable dans le PATH, tenter des chemins locaux usuels.
if [ "$MYSQLDUMP" = "mysqldump" ] && ! command -v mysqldump &> /dev/null; then
    for candidate in \
        /c/wamp64/bin/mysql/*/bin/mysqldump.exe \
        /c/wamp64/bin/mariadb/*/bin/mysqldump.exe \
        /mingw64/bin/mysqldump.exe \
        /usr/bin/mysqldump
    do
        if [ -x "$candidate" ]; then
            MYSQLDUMP="$candidate"
            log_info "mysqldump détecté automatiquement: $MYSQLDUMP"
            break
        fi
    done
fi

# Vérifier disponibilité de mysqldump (chemin explicite ou PATH).
if [ ! -x "$MYSQLDUMP" ] && ! command -v "$MYSQLDUMP" &> /dev/null
then
    log_error "mysqldump n'est pas accessible via MYSQLDUMP=$MYSQLDUMP"
    exit 1
fi

# Vérifier ssh/scp seulement si mode distant actif.
if [ "$ENABLE_REMOTE_SYNC" = "true" ]; then
    if ! command -v scp &> /dev/null || ! command -v ssh &> /dev/null
    then
        log_error "ssh/scp n'est pas installé ou n'est pas dans le PATH."
        exit 1
    fi
fi

# Créer le dossier de backup si besoin.
if [ ! -d "$BACKUP_DIR" ]; then
    log_info "Dossier backup inexistant. Création..."
    mkdir -p "$BACKUP_DIR" || {
        log_error "Impossible de créer le dossier $BACKUP_DIR"
        exit 1
    }
fi

# Nom du fichier backup compressé.
BACKUP_FILE="$BACKUP_DIR/${DB_NAME}_$DATE.sql.gz"

# Dump SQL + compression gzip en sortie.
"$MYSQLDUMP" -u "$DB_USER" $MYSQL_PWD_OPT \
--routines \
--triggers \
--events \
"$DB_NAME" 2>error.log | gzip > "$BACKUP_FILE"

# Vérifier le statut du dump.
if [ $? -eq 0 ]; then
    log_info "Sauvegarde réussie : $BACKUP_FILE"
else
    log_error "Erreur lors de la sauvegarde."
    cat error.log | tee -a "$LOG_FILE"
    rm -f "$BACKUP_FILE"
    exit 1
fi

# Envoyer le backup en distant si activé.
if [ "$ENABLE_REMOTE_SYNC" = "true" ]; then
    log_info "Envoi du backup vers serveur externe..."

    # Préparer options ssh/scp.
    SSH_OPTS="-p $REMOTE_PORT"
    if [ -n "$SSH_KEY" ]; then
        SSH_OPTS="$SSH_OPTS -i $SSH_KEY"
    fi

    # Créer le dossier distant.
    ssh $SSH_OPTS "$REMOTE_USER@$REMOTE_HOST" "mkdir -p '$REMOTE_DIR'" || {
        log_error "Impossible de créer le dossier distant $REMOTE_DIR"
        exit 1
    }

    # Copier le backup vers le serveur.
    scp $SSH_OPTS "$BACKUP_FILE" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/" || {
        log_error "Échec de l'envoi du backup vers le serveur externe."
        exit 1
    }

    log_info "Envoi externe réussi."
fi

# Nettoyer les backups locaux de plus de 7 jours.
find "$BACKUP_DIR" -type f -name "*.sql.gz" -mtime +7 -delete
log_info "Nettoyage des sauvegardes > 7 jours effectué."
