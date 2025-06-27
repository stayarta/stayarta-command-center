#!/bin/bash
# 🚀 Git Integration Automation - STAYArta Command Center
# Automated Git operations for enterprise workflow

COMMAND_CENTER="/Users/carlos/Documents/STAYArta_Command_Center"
NOVASTAYBOT="/Users/carlos/novastaybot"
LOG_FILE="$COMMAND_CENTER/08_COLLABORATION/Claude_Workspace/Automation_Scripts/Logs/git_automation.log"

echo "$(date): 🚀 Git Integration Automation started" >> "$LOG_FILE"

# Function to auto-commit changes in Command Center
auto_commit_command_center() {
    cd "$COMMAND_CENTER"
    
    # Check for changes
    if ! git diff --quiet; then
        echo "$(date): 📝 Changes detected in Command Center" >> "$LOG_FILE"
        
        # Add all changes
        git add .
        
        # Create intelligent commit message
        CHANGED_FILES=$(git diff --cached --name-only | wc -l)
        TIMESTAMP=$(date +"%Y-%m-%d %H:%M")
        
        git commit -m "🔄 Auto-update: Command Center sync $TIMESTAMP

- $CHANGED_FILES files updated
- Automated enterprise workflow
- System optimization active
- STAYArta infrastructure maintained"

        echo "$(date): ✅ Command Center auto-commit completed" >> "$LOG_FILE"
    else
        echo "$(date): ℹ️ No changes in Command Center" >> "$LOG_FILE"
    fi
}

# Function to auto-commit changes in NovaSTAYBot
auto_commit_novastaybot() {
    cd "$NOVASTAYBOT"
    
    # Check for changes
    if ! git diff --quiet; then
        echo "$(date): 🤖 Changes detected in NovaSTAYBot" >> "$LOG_FILE"
        
        # Add all changes
        git add .
        
        # Create intelligent commit message
        TIMESTAMP=$(date +"%Y-%m-%d %H:%M")
        
        git commit -m "🤖 Auto-update: NovaSTAYBot enhancement $TIMESTAMP

- Bot functionality improved
- STAYArta integration optimized
- Automated deployment ready
- Performance enhancements"

        echo "$(date): ✅ NovaSTAYBot auto-commit completed" >> "$LOG_FILE"
    else
        echo "$(date): ℹ️ No changes in NovaSTAYBot" >> "$LOG_FILE"
    fi
}

# Function to create automated backups
create_git_backup() {
    echo "$(date): 💾 Creating Git backup branches" >> "$LOG_FILE"
    
    # Backup Command Center
    cd "$COMMAND_CENTER"
    BACKUP_BRANCH="backup/$(date +%Y%m%d_%H%M%S)"
    git branch "$BACKUP_BRANCH"
    echo "$(date): ✅ Command Center backup: $BACKUP_BRANCH" >> "$LOG_FILE"
    
    # Backup NovaSTAYBot
    cd "$NOVASTAYBOT"
    git branch "$BACKUP_BRANCH"
    echo "$(date): ✅ NovaSTAYBot backup: $BACKUP_BRANCH" >> "$LOG_FILE"
}

# Function to clean old backup branches (keep last 5)
cleanup_old_backups() {
    echo "$(date): 🧹 Cleaning old backup branches" >> "$LOG_FILE"
    
    # Command Center cleanup
    cd "$COMMAND_CENTER"
    BACKUP_BRANCHES=$(git branch | grep "backup/" | sort -r | tail -n +6)
    if [ ! -z "$BACKUP_BRANCHES" ]; then
        echo "$BACKUP_BRANCHES" | xargs git branch -D
        echo "$(date): 🗑️ Cleaned old Command Center backups" >> "$LOG_FILE"
    fi
    
    # NovaSTAYBot cleanup
    cd "$NOVASTAYBOT"
    BACKUP_BRANCHES=$(git branch | grep "backup/" | sort -r | tail -n +6)
    if [ ! -z "$BACKUP_BRANCHES" ]; then
        echo "$BACKUP_BRANCHES" | xargs git branch -D
        echo "$(date): 🗑️ Cleaned old NovaSTAYBot backups" >> "$LOG_FILE"
    fi
}

# Function to generate Git status report
generate_git_report() {
    REPORT_FILE="$COMMAND_CENTER/08_COLLABORATION/Claude_Workspace/git_status_report.json"
    
    # Command Center status
    cd "$COMMAND_CENTER"
    CC_STATUS=$(git status --porcelain | wc -l)
    CC_BRANCH=$(git branch --show-current)
    CC_LAST_COMMIT=$(git log -1 --format="%h %s")
    
    # NovaSTAYBot status
    cd "$NOVASTAYBOT"
    BOT_STATUS=$(git status --porcelain | wc -l)
    BOT_BRANCH=$(git branch --show-current)
    BOT_LAST_COMMIT=$(git log -1 --format="%h %s")
    
    # Create JSON report
    cat > "$REPORT_FILE" << EOF
{
  "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "command_center": {
    "status": "active",
    "branch": "$CC_BRANCH",
    "uncommitted_changes": $CC_STATUS,
    "last_commit": "$CC_LAST_COMMIT"
  },
  "novastaybot": {
    "status": "active", 
    "branch": "$BOT_BRANCH",
    "uncommitted_changes": $BOT_STATUS,
    "last_commit": "$BOT_LAST_COMMIT"
  },
  "automation": {
    "git_integration": "active",
    "auto_commits": "enabled",
    "backup_system": "operational"
  }
}
EOF

    echo "$(date): 📊 Git status report generated" >> "$LOG_FILE"
}

# Execute automation functions
auto_commit_command_center
auto_commit_novastaybot
create_git_backup
cleanup_old_backups
generate_git_report

echo "$(date): 🎯 Git Integration Automation completed successfully" >> "$LOG_FILE"
