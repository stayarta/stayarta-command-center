#!/bin/bash
# 🔄 Auto-Organizer Daemon - Monitoreo continuo

COMMAND_CENTER="$HOME/Documents/STAYArta_Command_Center"
SOURCE_DIR="$HOME/Documents/Documentos"
LOG_FILE="$COMMAND_CENTER/08_COLLABORATION/Claude_Workspace/Automation_Scripts/Logs/auto_organizer.log"

echo "$(date): 🔄 Auto-Organizer iniciado" >> "$LOG_FILE"

# Función de organización inteligente
organize_file() {
    local file="$1"
    local filename=$(basename "$file")
    local lowercase_name=$(echo "$filename" | tr '[:upper:]' '[:lower:]')
    
    # STAYArta específicos
    if [[ "$lowercase_name" =~ (stayarta|stay.*arta) ]]; then
        if [[ "$lowercase_name" =~ (logo|brand) ]]; then
            mv "$file" "$COMMAND_CENTER/02_BRAND_ASSETS/Logos/Primary/"
        elif [[ "$lowercase_name" =~ (legal|contrato) ]]; then
            mv "$file" "$COMMAND_CENTER/01_EMPRESAS/STAYARTA_Venezuela/Legal/"
        elif [[ "$lowercase_name" =~ (factura|presupuesto) ]]; then
            mv "$file" "$COMMAND_CENTER/03_FINANCIALS/Presupuestos/2025/"
        else
            mv "$file" "$COMMAND_CENTER/08_COLLABORATION/Shared_Resources/"
        fi
        echo "$(date): ✅ Movido STAYArta: $filename" >> "$LOG_FILE"
        
    # Archivos IA
    elif [[ "$lowercase_name" =~ (claude|nova|chatgpt|ai.*generated) ]]; then
        if [[ "$lowercase_name" =~ claude ]]; then
            mv "$file" "$COMMAND_CENTER/10_AI_GENERATED/_BY_TOOL/Claude/Documents/"
        elif [[ "$lowercase_name" =~ nova ]]; then
            mv "$file" "$COMMAND_CENTER/10_AI_GENERATED/_BY_USE_CASE/NovaScripts/"
        else
            mv "$file" "$COMMAND_CENTER/10_AI_GENERATED/_BY_TOOL/ChatGPT/Documents/"
        fi
        echo "$(date): 🤖 Movido IA: $filename" >> "$LOG_FILE"
        
    # Documentos legales
    elif [[ "$lowercase_name" =~ (legal|contrato|contract|acuerdo) ]]; then
        if [[ "$lowercase_name" =~ (usa|llc) ]]; then
            mv "$file" "$COMMAND_CENTER/01_EMPRESAS/Carlos_Arta_LLC_USA/Legal/"
        else
            mv "$file" "$COMMAND_CENTER/01_EMPRESAS/STAYARTA_Venezuela/Legal/"
        fi
        echo "$(date): ⚖️ Movido Legal: $filename" >> "$LOG_FILE"
        
    # Documentos financieros
    elif [[ "$lowercase_name" =~ (factura|invoice|presupuesto|budget|bank) ]]; then
        if [[ "$lowercase_name" =~ (factura|invoice) ]]; then
            mv "$file" "$COMMAND_CENTER/03_FINANCIALS/Facturas/Recibidas/2025/"
        else
            mv "$file" "$COMMAND_CENTER/03_FINANCIALS/Presupuestos/2025/"
        fi
        echo "$(date): 💰 Movido Financiero: $filename" >> "$LOG_FILE"
    fi
}

# Monitorear Downloads
if [ -d "$HOME/Downloads" ]; then
    find "$HOME/Downloads" -type f -newer "$HOME/Downloads" -name "*stayarta*" -o -name "*carlos*arta*" -o -name "*logo*" -o -name "*factura*" -o -name "*contrato*" | while read file; do
        organize_file "$file"
    done
fi

# Monitorear Desktop
if [ -d "$HOME/Desktop" ]; then
    find "$HOME/Desktop" -type f -name "*stayarta*" -o -name "*claude*" -o -name "*nova*" | while read file; do
        organize_file "$file"
    done
fi

echo "$(date): ✅ Auto-Organizer ciclo completado" >> "$LOG_FILE"
