#!/bin/bash
# 💾 Smart Backup System - Backup inteligente automatizado

COMMAND_CENTER="$HOME/Documents/STAYArta_Command_Center"
BACKUP_BASE="$HOME/Library/CloudStorage/GoogleDrive-carlos@stayarta.com/My Drive/STAYArta_Backups"
DATE=$(date +"%Y-%m-%d")
TIME=$(date +"%H-%M")
BACKUP_DIR="$BACKUP_BASE/CommandCenter_$DATE"
LOG_FILE="$COMMAND_CENTER/08_COLLABORATION/Claude_Workspace/Automation_Scripts/Logs/backup.log"

echo "$(date): 💾 Iniciando Smart Backup" >> "$LOG_FILE"

# Crear directorio de backup
mkdir -p "$BACKUP_DIR"

# Backup incremental inteligente
rsync -av --delete \
    --exclude='.DS_Store' \
    --exclude='*.tmp' \
    --exclude='*.cache' \
    "$COMMAND_CENTER/" "$BACKUP_DIR/"

# Backup comprimido para archivos antiguos
if [ $(date +%d) = "01" ]; then  # Primer día del mes
    tar -czf "$BACKUP_BASE/Archive_$(date +%Y%m).tar.gz" "$BACKUP_DIR"
    echo "$(date): 📦 Backup mensual comprimido creado" >> "$LOG_FILE"
fi

# Limpiar backups antiguos (mantener últimos 7 días)
find "$BACKUP_BASE" -name "CommandCenter_*" -type d -mtime +7 -exec rm -rf {} \;

# Estadísticas
FILES_COUNT=$(find "$BACKUP_DIR" -type f | wc -l)
BACKUP_SIZE=$(du -sh "$BACKUP_DIR" | cut -f1)

echo "$(date): ✅ Backup completado - $FILES_COUNT archivos, $BACKUP_SIZE" >> "$LOG_FILE"

# Notificación
osascript -e "display notification \"Backup STAYArta completado: $BACKUP_SIZE\" with title \"Command Center\""
